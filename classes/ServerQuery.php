<?php

/**
 * The class for querying Minecraft server
 * Mostly a port from python of https://github.com/Dinnerbone/mcstatus
 *
 * @author Anton Smirnov <sandfox@sandfox.im>
 * @license MIT
 */

namespace SandFoxIM\Minecraft;

class ServerQuery
{
	const MAGIC_PREFIX = "\xFE\xFD";
	const PACKET_TYPE_CHALLENGE = 9;
	const PACKET_TYPE_QUERY = 0;

	public $HUMAN_READABLE_NAMES = array(
		'game_id'       => 'Game Name',
		'gametype'      => 'Game Type',
		'motd'          => 'Message of the Day',
		'hostname'      => 'Server Address',
		'hostport'      => 'Server Port',
		'map'           => 'Main World Name',
		'maxplayers'    => 'Maximum Players',
		'numplayers'    => 'Players Online',
		'players'       => 'List of Players',
		'plugins'       => 'List of Plugins',
		'raw_plugins'   => 'Raw Plugin Info',
		'software'      => 'Server Software',
		'version'       => 'Game Version',
	);

	private $host;
	private $port;
	private $id;
	private $id_packed;
	private $challenge;
	private $challenge_packed;
	private $retries;
	private $max_retries;
	private $ping;

	private $socket;

	public function __construct($host, $port = 25565, $timeout = 10, $id = 0, $retries = 2)
	{
		$this->host = $host;
		$this->port = $port;
		$this->id = $id;
		$this->id_packed = pack('N', $id);
		$this->challenge_packed = pack('N', 0);
		$this->retries = 0;
		$this->max_retries = $retries;

		$this->socket = socket_create(AF_INET, SOCK_DGRAM, 0);
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeout, 'usec' => 0));
	}

	private function sendRaw($data)
	{
		$rawdata = self::MAGIC_PREFIX . $data;
		socket_sendto($this->socket, $rawdata, strlen($rawdata), 0, $this->host, $this->port);
	}

	private function sendPacket($type, $data = '')
	{
		$this->sendRaw(pack('C', $type) . $this->id_packed . $this->challenge_packed . $data);
	}

	private function readPacket()
	{
		$result = socket_recvfrom($this->socket, $buff, 1460, 0, $name, $port);

		if ($result === false) {
			throw new NetworkException('Cannot obtain packet data');
		}

		$type = unpack('C', substr($buff, 0, 1));
		$id   = unpack('N', substr($buff, 1, 4));
		$data = substr($buff, 5);

		return array($type, $id, $data);
	}

	private function handshake($bypass_retries = false)
	{
		$start = microtime(true);

		$this->sendPacket(self::PACKET_TYPE_CHALLENGE);

		try {
			list(, , $buff) = $this->readPacket();
		} catch (NetworkException $e) {
			if ($bypass_retries === false) {
				$this->retries += 1;
			}

			if ($this->retries < $this->max_retries) {
				$this->handshake($bypass_retries);
				return;
			} else {
				throw $e;
			}
		}

		$this->ping = round((microtime(true)-$start)*1000);

		$this->challenge = intval(substr($buff, 0, -1));
		$this->challenge_packed = pack('N', $this->challenge);
	}

	public function getStatus()
	{
		if (empty($this->challenge)) {
			$this->handshake();
		}

		$this->sendPacket(self::PACKET_TYPE_QUERY);

		try {
			list(,, $buff) = $this->readPacket();
		} catch (NetworkException $e) {
			$this->handshake();
			return $this->getStatus();
		}

		$data = array();

		list(
			$data['motd'],
			$data['gametype'],
			$data['map'],
			$data['numplayers'],
			$data['maxplayers'],
			$buff
			) = explode("\x00", $buff, 6);

		list(, $data['hostport']) = unpack('v', substr($buff, 0, 2));

		$buff = substr($buff, 2);

		$data['hostname'] = substr($buff, 0, -1);

		$data['numplayers'] = intval($data['numplayers']);
		$data['maxplayers'] = intval($data['maxplayers']);

		$data['ping'] = $this->ping;

		return $data;
	}

	public function getRules()
	{
		if (empty($this->challenge)) {
			$this->handshake();
		}

		$this->sendPacket(self::PACKET_TYPE_QUERY, $this->id_packed);

		try {
			list(,, $buff) = $this->readPacket();
		} catch (NetworkException $e) {
			$this->retries += 1;

			if ($this->retries < $this->max_retries) {
				$this->handshake(true);
				return $this->getRules();
			} else {
				throw $e;
			}
		}

		$buff = substr($buff, 11); // splitnum + 2 ints
		list($items, $players) = explode("\x00\x00\x01player_\x00\x00", $buff); // I hope it works

		if (substr($items, 0, 8) === 'hostname') {
			$items = 'motd' . substr($items, 8);
		}

		$items = explode("\x00", $items);

		// it should mean data = dict(zip(items[::2], items[1::2]))
		$items_keys   = array();
		$items_values = array();
		for ($i = 0; $i < count($items);) {
			$items_keys   []= $items[$i++];
			$items_values []= $items[$i++];
		}

		$data = array_combine($items_keys, $items_values);

		$players = substr($players, 0, -2);

		if ($players) {
			$data['players'] = explode("\x00", $players);
		} else {
			$data['players'] = array();
		}

		foreach (array('numplayers', 'maxplayers', 'hostport') as $key) {
			if ($data[$key]) {
				$data[$key] = intval($data[$key]);
			}
		}

		$data['raw_motd'] = $data['motd'];
		$data['motd'] = $this->cleanMotd($data['motd']);

		$data['raw_plugins'] = $data['plugins'];

		list($data['software'], $data['plugins']) = $this->parsePlugins($data['raw_plugins']);

		$data['ping'] = $this->ping;

		return $data;
	}

	private function parsePlugins($raw)
	{
		$parts = explode(':', $raw, 2);
		$server = trim($parts[0]);
		$plugins = array();

		if (count($parts) === 2) {
			$plugins = explode(';', $parts[1]);
			$plugins = array_map(function($s) {
				return trim($s);
			}, $plugins);
		}

		return array($server, $plugins);
	}

	private function cleanMotd($motd)
	{
		return preg_replace('/&./', '', $motd);
	}
}
