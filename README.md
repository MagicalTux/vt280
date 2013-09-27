= VT-280

This is a Toshiba TEC automatic change machine commonly found in Japan (Yodobashi-Camera, etc).

It is actually made of two machines, one is for coins, the second one is for bills (both are connected together via a RS232 cable using proprietary connectors).

The PoS is connected to the device via a single RS232 cable.

== Communications

Communications are done over RS232, at 9600bps, 8 bits, even parity and 1 stop bits (9600/8E1).

Each frame sent by either the PoS or the device are prefixed by a single 0x02 byte, and suffixed by a 0x03 byte followed by a checksum of the whole frame.

The checksum is actually a XOR of each byte in the frame.


