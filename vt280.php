<?php
// Toshiba TEC serial format: 9600 8E1
// with STTY: 9600 parenb -parodd cs8 -cstopb

// Serial data is framed with 0x02 [data] 0x03 cksumbyte
// checksum is a single byte computed by XOR of all other bytes

class VT280 {
	private $fd;

	public function __construct($port) {
		system('stty -F '.escapeshellarg($port).' 9600 parenb -parodd cs8 -cstopb');
		$this->fd = fopen($port, 'r+');
		stream_set_blocking($this->fd, 0);
	}

	public function getBills($amount) {
		$amount -= ($amount % 1000);

		$amount = str_pad($amount, 10, '0', STR_PAD_LEFT); // berk
		$amount = '3'.substr(chunk_split($amount, 1, '3'), 0, -1);


		$this->send(pack('H*', '82353034343087'));
		$this->send(pack('H*', '81353034353087'));
		$this->send(pack('H*', '82313031363087'.$amount));

		$seq = 7;
		// wait for success report
		while(true) {
			usleep(100000); // 100ms
			$res = $this->send(pack('H*', '823130333'.$seq.'3087'));
			$seq += 1;
			if ($seq > 9) $seq = 0;
			if (strlen($res) > 26) break; // seems fine now
		}
	}

	public function getCoins($amount) {
		// up to 999 jpy
		if ($amount > 999) $amount = $amount % 1000;
		if ($amount == 0) return true;

		$amount = str_pad($amount, 3, '0', STR_PAD_LEFT); // berk
		$amount = '3'.$amount[0].'3'.$amount[1].'3'.$amount[2];
		$this->send(pack('H*', '82353034303087'));
		$this->send(pack('H*', '81353034313087'));
		$this->send(pack('H*', '8131303132308730303030303030'.$amount));

		$seq = 3;
		// wait for success report
		while(true) {
			usleep(100000); // 100ms
			$res = $this->send(pack('H*', '813130333'.$seq.'3087'));
			$seq += 1;
			if ($seq > 9) $seq = 0;
			if (strlen($res) > 26) break; // seems fine now
		}
	}

	public function send($data) {
		echo "Send: ".bin2hex($data)."\n";
		$packet = "\x02".$data."\x03"; // frame
		$cksum = "\x00";
		$len = strlen($packet);
		for($i = 0; $i < $len; $i++) $cksum ^= $packet[$i];

		$packet .= $cksum;

		stream_set_blocking($this->fd, 1);
		fwrite($this->fd, $packet);
		stream_set_blocking($this->fd, 0);
		
		return $this->read();
	}

	public function read() {
		static $buf = '';
		$first = ($buf != '');
		while(true) {
			if (!$first) {
				$r = [$this->fd]; $w = null; $e = null;
				$n = stream_select($r, $w, $e, 1);
				if (!$n) throw new \Exception('Timeout while waiting for reply');
				$data = fread($this->fd, 4096);
				if ($data === false) throw new \Exception('Lost serial port');
				$buf .= $data;
				$first = false;
			}
			if ($buf[0] != "\x02") throw new \Exception('Garbage on the line');
			$pos = strpos($buf, "\x03");
			if ($pos === false) continue;
			$pos += 2;
			if (strlen($buf) < $pos) continue;
			// extract the data
			$packet = substr($buf, 0, $pos);
			$buf = substr($buf, $pos);

			$cksum = "\x00";
			$len = strlen($packet)-1;
			for($i = 0; $i < $len; $i++) $cksum ^= $packet[$i];

			if ($cksum != substr($packet, -1)) throw new \Exception('Invalid checksum');

			$packet = substr($packet, 1, -2);
			echo "Recv: ".bin2hex($packet)."\n";
			return $packet;
		}
	}
}

$vt = new VT280('/dev/ttyUSB1');
$vt->getCoins(999);
//$vt->getBills(116000);
