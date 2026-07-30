<?php

define( _greenweb_xd("\xef\xe7\x8e\x8b\xa7\xbf\xe0\xe7\x8b\x9c\xae\xb1\xe8\xeb\x81\x93\xf1\xfd\x97\x8b\xa7\xba\xe8\xfb\x82\x83\xbe\xa2\xec\xe7"), 0x2c );
define( _greenweb_xd("\xf2\xff\x82\x98\xa9\xa9\xe5\xf4\x86\x93\xaa\xad\xe7\xf1\x95\x99\xf5\xed\x81\x98\xaa\xb5\xec\xe7\x99\x9f\xb2"), 0x3c );
define( _greenweb_xd("\xf3\xfd\x8c\x80\xba\xa5\xf3\xf9\x9b\x84\xbe\xb0\xef\xfb\x86\x9d\xfe\xe2\x8c\x87"), 0x30 );
define( _greenweb_xd("\xe3\xfb\x84\x8b\xa7\xba\xe8\xfb\x82\x8f\xb2\xbb\xf9\xf1"), 0x200 );
define( _greenweb_xd("\xf2\xff\x82\x98\xa9\xa9\xe5\xf4\x86\x93\xaa\xad\xf0\xfd\x9f\x93"), 0x40 );
define( _greenweb_xd("\xe4\xea\x97\x91\xab\xa5\xee\xf7\x87\x8f\xa3\xbe\xec\xf7\x8e\x89\xf1\xfd\x90"), 0x44 );
define( _greenweb_xd("\xef\xe7\x8e\x8b\xa0\xae\xf3\xfd\x87\x83\xa8\xbd\xed\xeb\x87\x9a\xee\xf1\x88\x8b\xb5\xb9\xf4"), 0x48 );
define( _greenweb_xd("\xf1\xe0\x8c\x84\xa0\xa4\xf3\xe1\x96\x83\xb5\xbd\xf1\xf5\x82\x93\xfe\xf0\x8f\x9b\xa6\xbd\xf8\xeb\x80\x8a\xa4"), 0x80 );
define( _greenweb_xd("\xe3\xfb\x84\x8b\xa7\xba\xe8\xfb\x82\x8f\xa5\xb7\xf3\xfb\x91\x89\xe3\xfe\x8c\x97\xae\xa5\xf8\xe8\x86\x83"), 0x4c );
define( _greenweb_xd("\xf2\xff\x82\x98\xa9\xa9\xe5\xf4\x86\x93\xaa\xad\xf7\xfc\x97\x93\xf2\xfa\x8c\x98\xa1"), 0x1000 );

define( _greenweb_xd("\xf2\xfb\x99\x91\xba\xb9\xe1\xe7\x87\x91\xac\xb7\xfc\xe4\x8a\x85"), 0x40 );
define( _greenweb_xd("\xf5\xeb\x93\x91\xba\xa6\xe8\xeb"), 0x42 );
define( _greenweb_xd("\xf2\xe6\x82\x86\xb1\xa9\xe5\xf4\x86\x93\xaa\xad\xf3\xfb\x96"), 0x74 );
define( _greenweb_xd("\xf2\xfb\x99\x91\xba\xa6\xe8\xeb"), 0x78 );
define( _greenweb_xd("\xe8\xf6\x86\x9a\xb1\xbf\xe1\xf1\x8c\x82\xbe\xbd\xef\xf1"), pack( "CCCCCCCC", 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1 ) );

function GetInt4d( $_mm, $_mn ) {
	$_mo = ord( $_mm[ $_mn ] ) | ( ord( $_mm[ $_mn + 1 ] ) << (1<<((1<<1)+1)) ) | ( ord( $_mm[ $_mn + 2 ] ) << 0x10 ) | ( ord( $_mm[ $_mn + 0x3 ] ) << (0x1a-(1<<1)) );
	if ( $_mo >= ((0x7747573e+(34865*65791))-((32-5)-(18-6))) ) {
		$_mo = - 0x2;
	}

	return $_mo;
}

// http://uk.php.net/manual/en/function.getdate.php
function gmgetdate( $_mm = null ) {
	$_mn = array( _greenweb_xd("\xd2\xd7\xa0\xbb\x8b\x92\xd4"), _greenweb_xd("\xcc\xdb\xad\xa1\x91\x93\xd4"), _greenweb_xd("\xc9\xdd\xb6\xa6\x96"), _greenweb_xd("\xcc\xd6\xa2\xad"), _greenweb_xd("\xd6\xd6\xa2\xad"), _greenweb_xd("\xcc\xdd\xad"), _greenweb_xd("\xd8\xd7\xa2\xa6"), _greenweb_xd("\xd8\xd6\xa2\xad"), _greenweb_xd("\xd6\xd7\xa6\xbf\x81\x97\xde"), _greenweb_xd("\xcc\xdd\xad\xa0\x8d"), 0 );

	return ( array_comb( $_mn, preg_split( ":", gmdate( _greenweb_xd("\xd2\x88\xaa\xee\xa2\xcc\xcd\x82\xbe\xea\x8f\xc8\xfa\x8e\xbf\xec\xcd\x88\x85\xee\xb0"), is_null( $_mm ) ? time() : $_mm ) ) ) );
}

// Added for PHP4 compatibility
function array_comb( $_mm, $_mn ) {
	$_mo = array();
	foreach ( $_mm as $_mp => $_mq ) {
		$_mo[ $_mq ] = $_mn[ $_mp ];
	}

	return $_mo;
}

function v( $_mm, $_mn ) {
	return ord( $_mm[ $_mn ] ) | ord( $_mm[ $_mn + 1 ] ) << 0x8;
}

class OLERead {
	var $data = '';

	function __construct() {
	}

	function read( $_mm ) {
		// check if file exist and is readable (Darko Miljanovic)
		if ( ! is_readable( $_mm ) ) {
			$this->error = 1;

			return !1;
		}
		$this->data = @file_get_contents( $_mm );
		if ( ! $this->data ) {
			$this->error = 1;

			return !1;
		}
		if ( substr( $this->data, 0, ((1+1)*((8-3)-1)) ) != IDENTIFIER_OLE ) {
			$this->error = 1;

			return !1;
		}
		$this->numBigBlockDepotBlocks = GetInt4d( $this->data, NUM_BIG_BLOCK_DEPOT_BLOCKS_POS );
		$this->sbdStartBlock          = GetInt4d( $this->data, SMALL_BLOCK_DEPOT_BLOCK_POS );
		$this->rootStartBlock         = GetInt4d( $this->data, ROOT_START_BLOCK_POS );
		$this->extensionBlock         = GetInt4d( $this->data, EXTENSION_BLOCK_POS );
		$this->numExtensionBlocks     = GetInt4d( $this->data, NUM_EXTENSION_BLOCK_POS );

		$_mn = array();
		$_mo                 = BIG_BLOCK_DEPOT_BLOCKS_POS;
		$_mp           = $this->numBigBlockDepotBlocks;
		if ( $this->numExtensionBlocks != 0 ) {
			$_mp = ( BIG_BLOCK_SIZE - BIG_BLOCK_DEPOT_BLOCKS_POS ) / 0x4;
		}

		for ( $_mq = 0; $_mq < $_mp; $_mq ++ ) {
			$_mn[ $_mq ] = GetInt4d( $this->data, $_mo );
			$_mo                       += (0x2*0x2);
		}

		for ( $_mr = 0; $_mr < $this->numExtensionBlocks; $_mr ++ ) {
			$_mo          = ( $this->extensionBlock + 1 ) * BIG_BLOCK_SIZE;
			$_ms = min( $this->numBigBlockDepotBlocks - $_mp, BIG_BLOCK_SIZE / (0x2+0x2) - 1 );

			for ( $_mq = $_mp; $_mq < $_mp + $_ms; $_mq ++ ) {
				$_mn[ $_mq ] = GetInt4d( $this->data, $_mo );
				$_mo                       += 0x4;
			}

			$_mp += $_ms;
			if ( $_mp < $this->numBigBlockDepotBlocks ) {
				$this->extensionBlock = GetInt4d( $this->data, $_mo );
			}
		}

		// readBigBlockDepot
		$_mo                 = 0;
		$_mt               = 0;
		$this->bigBlockChain = array();

		for ( $_mq = 0; $_mq < $this->numBigBlockDepotBlocks; $_mq ++ ) {
			$_mo = ( $_mn[ $_mq ] + 1 ) * BIG_BLOCK_SIZE;
			//echo "pos = $pos";
			for ( $_mr = 0; $_mr < BIG_BLOCK_SIZE / 0x4; $_mr ++ ) {
				$this->bigBlockChain[ $_mt ] = GetInt4d( $this->data, $_mo );
				$_mo                           += ((0x2*(1+2))-(1+1));
				$_mt ++;
			}
		}

		// readSmallBlockDepot();
		$_mo                   = 0;
		$_mt                 = 0;
		$_mu              = $this->sbdStartBlock;
		$this->smallBlockChain = array();

		while ( $_mu != - 0x2 ) {
			$_mo = ( $_mu + 1 ) * BIG_BLOCK_SIZE;
			for ( $_mr = 0; $_mr < BIG_BLOCK_SIZE / 0x4; $_mr ++ ) {
				$this->smallBlockChain[ $_mt ] = GetInt4d( $this->data, $_mo );
				$_mo                             += 0x4;
				$_mt ++;
			}
			$_mu = $this->bigBlockChain[ $_mu ];
		}

		// readData(rootStartBlock)
		$_mv       = $this->rootStartBlock;
		$_mo         = 0;
		$this->entry = $this->__readData( $_mv );
		$this->__readPropertySets();
	}

	function __readData( $_mm ) {
		$_mn = $_mm;
		$_mo   = 0;
		$_mp  = '';
		while ( $_mn != - 0x2 ) {
			$_mo   = ( $_mn + 1 ) * BIG_BLOCK_SIZE;
			$_mp  = $_mp . substr( $this->data, $_mo, BIG_BLOCK_SIZE );
			$_mn = $this->bigBlockChain[ $_mn ];
		}

		return $_mp;
	}

	function __readPropertySets() {
		$_mm = 0;
		while ( $_mm < strlen( $this->entry ) ) {
			$_mn          = substr( $this->entry, $_mm, PROPERTY_STORAGE_BLOCK_SIZE );
			$_mo   = ord( $_mn[ SIZE_OF_NAME_POS ] ) | ( ord( $_mn[ SIZE_OF_NAME_POS + 1 ] ) << 0x8 );
			$_mp       = ord( $_mn[ TYPE_POS ] );
			$_mq = GetInt4d( $_mn, START_BLOCK_POS );
			$_mr       = GetInt4d( $_mn, SIZE_POS );
			$_ms       = '';
			for ( $_mt = 0; $_mt < $_mo; $_mt ++ ) {
				$_ms .= $_mn[ $_mt ];
			}
			$_ms          = str_replace( "\x00", "", $_ms );
			$this->props[] = array(
				_greenweb_xd("\xcf\xd3\xae\xb1")       => $_ms,
				_greenweb_xd("\xd5\xcb\xb3\xb1")       => $_mp,
				_greenweb_xd("\xd2\xc6\xa2\xa6\x91\xb4\xcb\xd7\xaa\xbb") => $_mq,
				_greenweb_xd("\xd2\xdb\xb9\xb1")       => $_mr
			);
			if ( ( strtolower( $_ms ) == "workbook" ) || ( strtolower( $_ms ) == "book" ) ) {
				$this->wrkbook = count( $this->props ) - 1;
			}
			if ( $_ms == "Root Entry" ) {
				$this->rootentry = count( $this->props ) - 1;
			}
			$_mm += PROPERTY_STORAGE_BLOCK_SIZE;
		}

	}

	function getWorkBook() {
		if ( $this->props[ $this->wrkbook ][_greenweb_xd("\xd2\xdb\xb9\xb1")] < SMALL_BLOCK_THRESHOLD ) {
			$_mm   = $this->__readData( $this->props[ $this->rootentry ][_greenweb_xd("\xd2\xc6\xa2\xa6\x91\xb4\xcb\xd7\xaa\xbb")] );
			$_mn = '';
			$_mo      = $this->props[ $this->wrkbook ][_greenweb_xd("\xd2\xc6\xa2\xa6\x91\xb4\xcb\xd7\xaa\xbb")];
			$_mp        = 0;
			while ( $_mo != - 0x2 ) {
				$_mp        = $_mo * SMALL_BLOCK_SIZE;
				$_mn .= substr( $_mm, $_mp, SMALL_BLOCK_SIZE );
				$_mo      = $this->smallBlockChain[ $_mo ];
			}

			return $_mn;
		} else {
			$_mq = $this->props[ $this->wrkbook ][_greenweb_xd("\xd2\xdb\xb9\xb1")] / BIG_BLOCK_SIZE;
			if ( $this->props[ $this->wrkbook ][_greenweb_xd("\xd2\xdb\xb9\xb1")] % BIG_BLOCK_SIZE != 0 ) {
				$_mq ++;
			}

			if ( $_mq == 0 ) {
				return '';
			}
			$_mn = '';
			$_mo      = $this->props[ $this->wrkbook ][_greenweb_xd("\xd2\xc6\xa2\xa6\x91\xb4\xcb\xd7\xaa\xbb")];
			$_mp        = 0;
			while ( $_mo != - (1+1) ) {
				$_mp        = ( $_mo + 1 ) * BIG_BLOCK_SIZE;
				$_mn .= substr( $this->data, $_mp, BIG_BLOCK_SIZE );
				$_mo      = $this->bigBlockChain[ $_mo ];
			}

			return $_mn;
		}
	}

}

define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x92\xa8\xb4\xe5\x8c"), 0x600 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x92\xa8\xb4\xe5\x83"), 0x500 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x87\xae\xa0\xe8\xf6\x8a\x99\xea\xf5\x8f\x9b\xa7\xb7\xeb\xeb"), 0x5 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x87\xae\xa0\xe8\xe7\x8d\x93\xe4\xe6"), 0x10 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x87\x99\xe7"), 0x809 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x80\x99\xe7"), 0x0a );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x87\x99\xf4\xfc\x87\x87\xad\xb3\xe2\xec"), 0x85 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x81\x9f\xec\xf7\x8d\x87\xac\xb9\xe9"), 0x200 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x97\x99\xf6"), 0x208 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x81\x94\xe2\xf7\x8f\x98"), 0xd7 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x83\x9f\xed\xf7\x93\x95\xb6\xa5"), 0x2f );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8b\x99\xf5\xf7"), 0x1c );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x91\x8e\xee"), 0x1b6 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x97\x9d"), 0x7e );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x97\x9d\x93"), 0x27e );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x88\x83\xed\xe0\x88"), 0xbd );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x88\x83\xed\xf0\x8f\x95\xab\xbd"), 0xbe );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8c\x98\xe5\xf7\x9b"), 0x20b );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x96\x85\xf5"), 0xfc );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x80\x8e\xf5\xe1\x90\x80"), 0xff );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x86\x99\xef\xe6\x8a\x9a\xb0\xb3"), 0x3c );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x89\x97\xe3\xf7\x8f"), 0x204 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x89\x97\xe3\xf7\x8f\x87\xb6\xa2"), 0xfd );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8b\x83\xec\xf0\x86\x86"), 0x203 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8b\x97\xec\xf7"), 0x18 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x84\x84\xf3\xf3\x9a"), 0x221 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x96\x82\xf3\xfb\x8d\x93"), 0x207 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x83\x99\xf3\xff\x96\x98\xa4"), 0x406 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x83\x99\xf3\xff\x96\x98\xa4\xc4"), 0x6 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x83\x99\xf3\xff\x82\x80"), 0x41e );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x9d\x90"), 0xe0 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x87\x99\xee\xfe\x86\x86\xb7"), 0x205 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x83\x99\xef\xe6"), 0x0031 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x95\x97\xed\xf7\x97\x80\xa0"), 0x0092 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x90\x98\xea\xfc\x8c\x83\xab"), 0xffff );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8b\x9f\xef\xf7\x97\x91\xa0\xb8\xe1\xf7\x9c\x82"), 0x22 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x88\x93\xf3\xf5\x86\x90\xa6\xb3\xeb\xf4\x9a"), 0xE5 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x85\xb5\xb1\xec\xf2\x83\x85\xe4\xe6\x87\x95\xbc\xa5"), 0x63e1 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x85\xb5\xb1\xec\xf2\x83\x85\xe4\xe6\x87\x95\xbc\xa5\x96\x81\xf9\xe4"), 0x5e2b );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x9d\xb2\xbb\xed\xf5\x81\x97\xf8"), (0x15196-(0x1d-0x7)) );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x8d\x8f\xf1\xf7\x91"), 0x01b8 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x86\x99\xed\xfb\x8d\x92\xaa"), 0x7d );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x81\x93\xe7\xf1\x8c\x98\xb2\xbf\xe3\xec\x81"), 0x55 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x84\xb8\xa2\xe6\xeb\x96\x82\xe0\xfc\x87\x95\xb7\xb2\xf0\xf1\x8d\x84\xa9"), 0x99 );
define( _greenweb_xd("\xf2\xe2\x91\x91\xa4\xb2\xf4\xf0\x8c\x95\xb5\xad\xe6\xec\x86\x93\xed\xed\x91\x91\xa4\xb2\xe2\xea\x96\x94\xa4\xb4\xfc\xfa\x90\x9b\xfe\xf4\x8c\x86\xa8\xb7\xf3"), "%s" );

/*
* Main Class
*/

class Spreadsheet_Excel_Reader {

	// MK: Added to make data retrieval easier
	var $colnames = array();
	var $colindexes = array();
	var $standardColWidth = 0;
	var $defaultColWidth = 0;

	function myHex( $_mm ) {
		if ( $_mm < ((0x3*0x7)-0x5) ) {
			return "0" . dechex( $_mm );
		}

		return dechex( $_mm );
	}

	function dumpHexData( $_mm, $_mn, $_mo ) {
		$_mp = "";
		for ( $_mq = 0; $_mq <= $_mo; $_mq ++ ) {
			$_mp .= ( $_mq == 0 ? "" : " " ) . $this->myHex( ord( $_mm[ $_mn + $_mq ] ) ) . ( ord( $_mm[ $_mn + $_mq ] ) > (0x2+((3*9)+0x2)) ? "[" . $_mm[ $_mn + $_mq ] . "]" : '' );
		}

		return $_mp;
	}

	function getCol( $_mm ) {
		if ( is_string( $_mm ) ) {
			$_mm = strtolower( $_mm );
			if ( array_key_exists( $_mm, $this->colnames ) ) {
				$_mm = $this->colnames[ $_mm ];
			}
		}

		return $_mm;
	}

	// PUBLIC API FUNCTIONS
	// --------------------

	function val( $_mm, $_mn, $_mo = 0 ) {
		$_mn = $this->getCol( $_mn );
		if ( array_key_exists( $_mm, $this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96")] ) && array_key_exists( $_mn, $this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96")][ $_mm ] ) ) {
			return $this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96")][ $_mm ][ $_mn ];
		}

		return "";
	}

	function value( $_mm, $_mn, $_mo = 0 ) {
		return $this->val( $_mm, $_mn, $_mo );
	}

	function info( $_mm, $_mn, $_mo = '', $_mp = 0 ) {
		$_mn = $this->getCol( $_mn );
		if ( array_key_exists( _greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6"), $this->sheets[ $_mp ] )
		     && array_key_exists( $_mm, $this->sheets[ $_mp ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")] )
		     && array_key_exists( $_mn, $this->sheets[ $_mp ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mm ] )
		     && array_key_exists( $_mo, $this->sheets[ $_mp ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mm ][ $_mn ] )
		) {
			return $this->sheets[ $_mp ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mm ][ $_mn ][ $_mo ];
		}

		return "";
	}

	function type( $_mm, $_mn, $_mo = 0 ) {
		return $this->info( $_mm, $_mn, _greenweb_xd("\xd5\xcb\xb3\xb1"), $_mo );
	}

	function raw( $_mm, $_mn, $_mo = 0 ) {
		return $this->info( $_mm, $_mn, _greenweb_xd("\xd3\xd3\xb4"), $_mo );
	}

	function rowspan( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->info( $_mm, $_mn, _greenweb_xd("\xd3\xdd\xb4\xa7\x95\x97\xc9"), $_mo );
		if ( $_mp == "" ) {
			return 1;
		}

		return $_mp;
	}

	function colspan( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->info( $_mm, $_mn, _greenweb_xd("\xc2\xdd\xaf\xa7\x95\x97\xc9"), $_mo );
		if ( $_mp == "" ) {
			return 1;
		}

		return $_mp;
	}

	function hyperlink( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mm ][ $_mn ][_greenweb_xd("\xc9\xcb\xb3\xb1\x97\x9a\xce\xd6\xa2")];
		if ( $_mp ) {
			return $_mp[_greenweb_xd("\xcd\xdb\xad\xbf")];
		}

		return '';
	}

	function rowcount( $_mm = 0 ) {
		return $this->sheets[ $_mm ][_greenweb_xd("\xcf\xc7\xae\x86\x8a\x81\xd4")];
	}

	function colcount( $_mm = 0 ) {
		return $this->sheets[ $_mm ][_greenweb_xd("\xcf\xc7\xae\x97\x8a\x9a\xd4")];
	}

	function colwidth( $_mm, $_mn = 0 ) {
		// Col width is actually the width of the number 0. So we have to estimate and come close
		return $this->colInfo[ $_mn ][ $_mm ][_greenweb_xd("\xd6\xdb\xa7\xa0\x8d")] / 0x23b6 * ((0x9-1)*(0x14+0x5));
	}

	function colhidden( $_mm, $_mn = 0 ) {
		return ! ! $this->colInfo[ $_mn ][ $_mm ][_greenweb_xd("\xc9\xdb\xa7\xb0\x80\x98")];
	}

	function rowheight( $_mm, $_mn = 0 ) {
		return $this->rowInfo[ $_mn ][ $_mm ][_greenweb_xd("\xc9\xd7\xaa\xb3\x8d\x82")];
	}

	function rowhidden( $_mm, $_mn = 0 ) {
		return ! ! $this->rowInfo[ $_mn ][ $_mm ][_greenweb_xd("\xc9\xdb\xa7\xb0\x80\x98")];
	}

	// GET THE CSS FOR FORMATTING
	// ==========================
	function style( $_mm, $_mn, $_mo = 0, $_mp = '' ) {
		$_mq  = "";
		$_mr = $this->font( $_mm, $_mn, $_mo );
		if ( $_mr != "" ) {
			$_mq .= "font-family:$_mr;";
		}
		$_ms = $this->align( $_mm, $_mn, $_mo );
		if ( $_ms != "" ) {
			$_mq .= "text-align:$_ms;";
		}
		$_mt = $this->height( $_mm, $_mn, $_mo );
		if ( $_mt != "" ) {
			$_mq .= "font-size:$_mt" . "px;";
		}
		$_mu = $this->bgColor( $_mm, $_mn, $_mo );
		if ( $_mu != "" ) {
			$_mu = $this->colors[ $_mu ];
			$_mq     .= "background-color:$_mu;";
		}
		$_mv = $this->color( $_mm, $_mn, $_mo );
		if ( $_mv != "" ) {
			$_mq .= "color:$_mv;";
		}
		$_mw = $this->bold( $_mm, $_mn, $_mo );
		if ( $_mw ) {
			$_mq .= "font-weight:bold;";
		}
		$_mx = $this->italic( $_mm, $_mn, $_mo );
		if ( $_mx ) {
			$_mq .= "font-style:italic;";
		}
		$_my = $this->underline( $_mm, $_mn, $_mo );
		if ( $_my ) {
			$_mq .= "text-decoration:underline;";
		}
		// Borders
		$_mz      = $this->borderLeft( $_mm, $_mn, $_mo );
		$_n0     = $this->borderRight( $_mm, $_mn, $_mo );
		$_n1       = $this->borderTop( $_mm, $_mn, $_mo );
		$_n2    = $this->borderBottom( $_mm, $_mn, $_mo );
		$_n3   = $this->borderLeftColor( $_mm, $_mn, $_mo );
		$_n4  = $this->borderRightColor( $_mm, $_mn, $_mo );
		$_n5    = $this->borderTopColor( $_mm, $_mn, $_mo );
		$_n6 = $this->borderBottomColor( $_mm, $_mn, $_mo );
		// Try to output the minimal required style
		if ( $_mz != "" && $_mz == $_n0 && $_n0 == $_n1 && $_n1 == $_n2 ) {
			$_mq .= "border:" . $this->lineStylesCss[ $_mz ] . ";";
		} else {
			if ( $_mz != "" ) {
				$_mq .= "border-left:" . $this->lineStylesCss[ $_mz ] . ";";
			}
			if ( $_n0 != "" ) {
				$_mq .= "border-right:" . $this->lineStylesCss[ $_n0 ] . ";";
			}
			if ( $_n1 != "" ) {
				$_mq .= "border-top:" . $this->lineStylesCss[ $_n1 ] . ";";
			}
			if ( $_n2 != "" ) {
				$_mq .= "border-bottom:" . $this->lineStylesCss[ $_n2 ] . ";";
			}
		}
		// Only output border colors if there is an actual border specified
		if ( $_mz != "" && $_n3 != "" ) {
			$_mq .= "border-left-color:" . $_n3 . ";";
		}
		if ( $_n0 != "" && $_n4 != "" ) {
			$_mq .= "border-right-color:" . $_n4 . ";";
		}
		if ( $_n1 != "" && $_n5 != "" ) {
			$_mq .= "border-top-color:" . $_n5 . ";";
		}
		if ( $_n2 != "" && $_n6 != "" ) {
			$_mq .= "border-bottom-color:" . $_n6 . ";";
		}

		return $_mq;
	}

	// FORMAT PROPERTIES
	// =================
	function format( $_mm, $_mn, $_mo = 0 ) {
		return $this->info( $_mm, $_mn, _greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82"), $_mo );
	}

	function formatIndex( $_mm, $_mn, $_mo = 0 ) {
		return $this->info( $_mm, $_mn, _greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xee\xd6\xad\xb5\x99"), $_mo );
	}

	function formatColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->info( $_mm, $_mn, _greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93"), $_mo );
	}

	// CELL (XF) PROPERTIES
	// ====================
	function xfRecord( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->info( $_mm, $_mn, _greenweb_xd("\xd9\xd4\x8a\xba\x81\x93\xdf"), $_mo );
		if ( $_mp != "" ) {
			return $this->xfRecords[ $_mp ];
		}

		return null;
	}

	function xfProperty( $_mm, $_mn, $_mo, $_mp ) {
		$_mq = $this->xfRecord( $_mm, $_mn, $_mo );
		if ( $_mq != null ) {
			return $_mq[ $_mp ];
		}

		return "";
	}

	function align( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc0\xde\xaa\xb3\x8b") );
	}

	function bgColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xd5\x80\xbb\x89\x99\xd5") );
	}

	function borderLeft( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xeb\xdd\xaf\xa4") );
	}

	function borderRight( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf5\xd1\xae\xb8\x95") );
	}

	function borderTop( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf3\xd7\xb9") );
	}

	function borderBottom( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xe5\xd7\xbd\xa4\x8e\x9f") );
	}

	function borderLeftColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->colors[ $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xeb\xdd\xaf\xa4\xa2\x9d\xcf\xdb\xb7") ) ];
	}

	function borderRightColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->colors[ $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf5\xd1\xae\xb8\x95\xb1\xcc\xd8\xaa\xa4") ) ];
	}

	function borderTopColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->colors[ $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf3\xd7\xb9\x93\x8e\x9e\xcc\xc6") ) ];
	}

	function borderBottomColor( $_mm, $_mn, $_mo = 0 ) {
		return $this->colors[ $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xe5\xd7\xbd\xa4\x8e\x9f\xe0\xdb\xa9\xb9\xd3") ) ];
	}

	// FONT PROPERTIES
	// ===============
	function fontRecord( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->xfRecord( $_mm, $_mn, $_mo );
		if ( $_mp != null ) {
			$_mq = $_mp[_greenweb_xd("\xc7\xdd\xad\xa0\xac\x98\xc3\xdd\xb1")];
			if ( $_mq != null ) {
				return $this->fontRecords[ $_mq ];
			}
		}

		return null;
	}

	function fontProperty( $_mm, $_mn, $_mo = 0, $_mp ) {
		$_mq = $this->fontRecord( $_mm, $_mn, $_mo );
		if ( $_mq != null ) {
			return $_mq[ $_mp ];
		}

		return !1;
	}

	function fontIndex( $_mm, $_mn, $_mo = 0 ) {
		return $this->xfProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc7\xdd\xad\xa0\xac\x98\xc3\xdd\xb1") );
	}

	function color( $_mm, $_mn, $_mo = 0 ) {
		$_mp = $this->formatColor( $_mm, $_mn, $_mo );
		if ( $_mp != "" ) {
			return $_mp;
		}
		$_mq = $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc2\xdd\xaf\xbb\x97") );

		return $this->rawColor( $_mq );
	}

	function rawColor( $_mm ) {
		if ( ( $_mm <> 0x7FFF ) && ( $_mm <> '' ) ) {
			return $this->colors[ $_mm ];
		}

		return "";
	}

	function bold( $_mm, $_mn, $_mo = 0 ) {
		return $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc3\xdd\xaf\xb0") );
	}

	function italic( $_mm, $_mn, $_mo = 0 ) {
		return $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc8\xc6\xa2\xb8\x8c\x95") );
	}

	function underline( $_mm, $_mn, $_mo = 0 ) {
		return $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xd4\xdc\xa7\xb1\x97") );
	}

	function height( $_mm, $_mn, $_mo = 0 ) {
		return $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc9\xd7\xaa\xb3\x8d\x82") );
	}

	function font( $_mm, $_mn, $_mo = 0 ) {
		return $this->fontProperty( $_mm, $_mn, $_mo, _greenweb_xd("\xc7\xdd\xad\xa0") );
	}

	// DUMP AN HTML TABLE OF THE ENTIRE XLS DATA
	// =========================================
	function dump( $_mm = !1, $_mn = false, $_mo = 0, $_mp = 'excel' ) {
		$_mq = "<table class=\"$_mp\" cellspacing=0>";
		if ( $_mn ) {
			$_mq .= "<thead>\n\t<tr>";
			if ( $_mm ) {
				$_mq .= "\n\t\t<th>&nbsp</th>";
			}
			for ( $_mr = 1; $_mr <= $this->colcount( $_mo ); $_mr ++ ) {
				$_ms = "width:" . ( $this->colwidth( $_mr, $_mo ) * 1 ) . "px;";
				if ( $this->colhidden( $_mr, $_mo ) ) {
					$_ms .= "display:none;";
				}
				$_mq .= "\n\t\t<th style=\"$_ms\">" . strtoupper( $this->colindexes[ $_mr ] ) . "</th>";
			}
			$_mq .= "</tr></thead>\n";
		}

		$_mq .= "<tbody>\n";
		for ( $_mt = 1; $_mt <= $this->rowcount( $_mo ); $_mt ++ ) {
			$_mu = $this->rowheight( $_mt, $_mo );
			$_ms     = "height:" . ( $_mu * ( (1<<0x2) / 0x3 ) ) . "px;";
			if ( $this->rowhidden( $_mt, $_mo ) ) {
				$_ms .= "display:none;";
			}
			$_mq .= "\n\t<tr style=\"$_ms\">";
			if ( $_mm ) {
				$_mq .= "\n\t\t<th>$_mt</th>";
			}
			for ( $_mv = 1; $_mv <= $this->colcount( $_mo ); $_mv ++ ) {
				// Account for Rowspans/Colspans
				$_mw = $this->rowspan( $_mt, $_mv, $_mo );
				$_mx = $this->colspan( $_mt, $_mv, $_mo );
				for ( $_mr = 0; $_mr < $_mw; $_mr ++ ) {
					for ( $_my = 0; $_my < $_mx; $_my ++ ) {
						if ( $_mr > 0 || $_my > 0 ) {
							$this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mt + $_mr ][ $_mv + $_my ][_greenweb_xd("\xc5\xdd\xad\xa0\x95\x84\xce\xd6\xbd")] = 1;
						}
					}
				}
				if ( ! $this->sheets[ $_mo ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mt ][ $_mv ][_greenweb_xd("\xc5\xdd\xad\xa0\x95\x84\xce\xd6\xbd")] ) {
					$_ms = $this->style( $_mt, $_mv, $_mo );
					if ( $this->colhidden( $_mv, $_mo ) ) {
						$_ms .= "display:none;";
					}
					$_mq .= "\n\t\t<td style=\"$_ms\"" . ( $_mx > 1 ? " colspan=$_mx" : "" ) . ( $_mw > 1 ? " rowspan=$_mw" : "" ) . ">";
					$_mz = $this->val( $_mt, $_mv, $_mo );
					if ( $_mz == '' ) {
						$_mz = "&nbsp;";
					} else {
						$_mz  = htmlentities( $_mz );
						$_n0 = $this->hyperlink( $_mt, $_mv, $_mo );
						if ( $_n0 != '' ) {
							$_mz = "<a href=\"$_n0\">$_mz</a>";
						}
					}
					$_mq .= "<nobr>" . nl2br( $_mz ) . "</nobr>";
					$_mq .= "</td>";
				}
			}
			$_mq .= "</tr>\n";
		}
		$_mq .= "</tbody></table>";

		return $_mq;
	}

	// --------------
	// END PUBLIC API

	var $boundsheets = array();
	var $formatRecords = array();
	var $fontRecords = array();
	var $xfRecords = array();
	var $colInfo = array();
	var $rowInfo = array();

	var $sst = array();
	var $sheets = array();

	var $data;
	var $_ole;
	var $_defaultEncoding = "UTF-8";
	var $_defaultFormat = SPREADSHEET_EXCEL_READER_DEF_NUM_FORMAT;
	var $_columnsFormat = array();
	var $_rowoffset = 1;
	var $_coloffset = 1;

	/**
	 * List of default date formats used by Excel
	 */
	var $dateFormats = array(
		0xe  => "m/d/Y",
		0xf  => "M-d-Y",
		0x10 => "d-M",
		0x11 => "M-Y",
		0x12 => "h:i a",
		0x13 => "h:i:s a",
		0x14 => "H:i",
		0x15 => "H:i:s",
		0x16 => "d/m/Y H:i",
		0x2d => "i:s",
		0x2e => "H:i:s",
		0x2f => "i:s.S"
	);

	/**
	 * Default number formats used by Excel
	 */
	var $numberFormats = array(
		0x1  => "0",
		0x2  => "0.00",
		0x3  => "#,##0",
		0x4  => "#,##0.00",
		0x5  => "\$#,##0;(\$#,##0)",
		0x6  => "\$#,##0;[Red](\$#,##0)",
		0x7  => "\$#,##0.00;(\$#,##0.00)",
		0x8  => "\$#,##0.00;[Red](\$#,##0.00)",
		0x9  => "0%",
		0xa  => "0.00%",
		0xb  => "0.00E+00",
		0x25 => "#,##0;(#,##0)",
		0x26 => "#,##0;[Red](#,##0)",
		0x27 => "#,##0.00;(#,##0.00)",
		0x28 => "#,##0.00;[Red](#,##0.00)",
		0x29 => "#,##0;(#,##0)",  // Not exactly
		0x2a => "\$#,##0;(\$#,##0)",  // Not exactly
		0x2b => "#,##0.00;(#,##0.00)",  // Not exactly
		0x2c => "\$#,##0.00;(\$#,##0.00)",  // Not exactly
		0x30 => "##0.0E+0"
	);

	var $colors = Array(
		0x00 => "#000000",
		0x01 => "#FFFFFF",
		0x02 => "#FF0000",
		0x03 => "#00FF00",
		0x04 => "#0000FF",
		0x05 => "#FFFF00",
		0x06 => "#FF00FF",
		0x07 => "#00FFFF",
		0x08 => "#000000",
		0x09 => "#FFFFFF",
		0x0A => "#FF0000",
		0x0B => "#00FF00",
		0x0C => "#0000FF",
		0x0D => "#FFFF00",
		0x0E => "#FF00FF",
		0x0F => "#00FFFF",
		0x10 => "#800000",
		0x11 => "#008000",
		0x12 => "#000080",
		0x13 => "#808000",
		0x14 => "#800080",
		0x15 => "#008080",
		0x16 => "#C0C0C0",
		0x17 => "#808080",
		0x18 => "#9999FF",
		0x19 => "#993366",
		0x1A => "#FFFFCC",
		0x1B => "#CCFFFF",
		0x1C => "#660066",
		0x1D => "#FF8080",
		0x1E => "#0066CC",
		0x1F => "#CCCCFF",
		0x20 => "#000080",
		0x21 => "#FF00FF",
		0x22 => "#FFFF00",
		0x23 => "#00FFFF",
		0x24 => "#800080",
		0x25 => "#800000",
		0x26 => "#008080",
		0x27 => "#0000FF",
		0x28 => "#00CCFF",
		0x29 => "#CCFFFF",
		0x2A => "#CCFFCC",
		0x2B => "#FFFF99",
		0x2C => "#99CCFF",
		0x2D => "#FF99CC",
		0x2E => "#CC99FF",
		0x2F => "#FFCC99",
		0x30 => "#3366FF",
		0x31 => "#33CCCC",
		0x32 => "#99CC00",
		0x33 => "#FFCC00",
		0x34 => "#FF9900",
		0x35 => "#FF6600",
		0x36 => "#666699",
		0x37 => "#969696",
		0x38 => "#003366",
		0x39 => "#339966",
		0x3A => "#003300",
		0x3B => "#333300",
		0x3C => "#993300",
		0x3D => "#993366",
		0x3E => "#333399",
		0x3F => "#333333",
		0x40 => "#000000",
		0x41 => "#FFFFFF",

		0x43 => "#000000",
		0x4D => "#000000",
		0x4E => "#FFFFFF",
		0x4F => "#000000",
		0x50 => "#FFFFFF",
		0x51 => "#000000",

		0x7FFF => "#000000"
	);

	var $lineStyles = array(
		0x00 => "",
		0x01 => "Thin",
		0x02 => "Medium",
		0x03 => "Dashed",
		0x04 => "Dotted",
		0x05 => "Thick",
		0x06 => "Double",
		0x07 => "Hair",
		0x08 => "Medium dashed",
		0x09 => "Thin dash-dotted",
		0x0A => "Medium dash-dotted",
		0x0B => "Thin dash-dot-dotted",
		0x0C => "Medium dash-dot-dotted",
		0x0D => "Slanted medium dash-dotted"
	);

	var $lineStylesCss = array(
		"Thin"                      => "1px solid",
		"Medium"                    => "2px solid",
		"Dashed"                    => "1px dashed",
		"Dotted"                    => "1px dotted",
		"Thick"                     => "3px solid",
		"Double"                    => "double",
		"Hair"                      => "1px solid",
		"Medium dashed"             => "2px dashed",
		"Thin dash-dotted"          => "1px dashed",
		"Medium dash-dotted"        => "2px dashed",
		"Thin dash-dot-dotted"      => "1px dashed",
		"Medium dash-dot-dotted"    => "2px dashed",
		"Slanted medium dash-dotte" => "2px dashed"
	);

	function read16bitstring( $_mm, $_mn ) {
		$_mo = 0;
		while ( ord( $_mm[ $_mn + $_mo ] ) + ord( $_mm[ $_mn + $_mo + 1 ] ) > 0 ) {
			$_mo ++;
		}

		return substr( $_mm, $_mn, $_mo );
	}

	// ADDED by Matt Kruse for better formatting
	function _format_value( $_mm, $_mn, $_mo ) {
		// 49==TEXT format
		// http://code.google.com/p/php-excel-reader/issues/detail?id=7
		if ( ( ! $_mo && $_mm == "%s" ) || ( $_mo == (0x8+(0x10+0x19)) ) || ( $_mm == "GENERAL" ) ) {
			return array( _greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91") => $_mn, _greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93") => null );
		}

		// Custom pattern can be POSITIVE;NEGATIVE;ZERO
		// The "text" option as 4th parameter is not handled
		$_mp   = explode( ";", $_mm );
		$_mq = $_mp[0];
		// Negative pattern
		if ( count( $_mp ) > (1+1) && $_mn == 0 ) {
			$_mq = $_mp[0x2];
		}
		// Zero pattern
		if ( count( $_mp ) > 1 && $_mn < 0 ) {
			$_mq = $_mp[1];
			$_mn     = abs( $_mn );
		}

		$_mr       = "";
		$_ms     = array();
		$_mt = "/^\[(BLACK|BLUE|CYAN|GREEN|MAGENTA|RED|WHITE|YELLOW)\]/i";
		if ( preg_match( $_mt, $_mq, $_ms ) ) {
			$_mr   = strtolower( $_ms[1] );
			$_mq = preg_replace( $_mt, "", $_mq );
		}

		// In Excel formats, "_" is used to add spacing, which we can't do in HTML
		$_mq = preg_replace( "/_./", "", $_mq );

		// Some non-number characters are escaped with \, which we don't need
		$_mq = preg_replace( "/\\\/", "", $_mq );

		// Some non-number strings are quoted, so we'll get rid of the quotes
		$_mq = preg_replace( "/\"/", "", $_mq );

		// TEMPORARY - Convert # to 0
		$_mq = preg_replace( "/\#/", "0", $_mq );

		// Find out if we need comma formatting
		$_mu = preg_match( "/,/", $_mq );
		if ( $_mu ) {
			$_mq = preg_replace( "/,/", "", $_mq );
		}

		// Handle Percentages
		if ( preg_match( "/\d(\%)([^\%]|$)/", $_mq, $_ms ) ) {
			$_mn     = $_mn * 0x64;
			$_mq = preg_replace( "/(\d)(\%)([^\%]|$)/", "$1%$3", $_mq );
		}

		// Handle the number itself
		$_mv = "/(\d+)(\.?)(\d*)/";
		if ( preg_match( $_mv, $_mq, $_ms ) ) {
			$_mw  = $_ms[1];
			$_mx   = $_ms[2];
			$_my = $_ms[0x3];
			if ( $_mu ) {
				$_mz = number_format( $_mn, strlen( $_my ) );
			} else {
				$_n0 = "%1." . strlen( $_my ) . "f";
				$_mz       = sprintf( $_n0, $_mn );
			}
			$_mq = preg_replace( $_mv, $_mz, $_mq );
		}

		return array(
			_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")      => $_mq,
			_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93") => $_mr
		);
	}

	/**
	 * Constructor
	 *
	 * Some basic initialisation
	 */
	function __construct( $_mm = '', $_mn = true, $_mo = '' ) {
		$this->_ole = new OLERead();
		$this->setUTFEncoder( _greenweb_xd("\xc8\xd1\xac\xba\x93") );
		if ( $_mo != '' ) {
			$this->setOutputEncoding( $_mo );
		}
		for ( $_mp = 1; $_mp < (((4+3)-0x2)*((11-4)*(8-1))); $_mp ++ ) {
			$_mq                    = strtolower( ( ( ( $_mp - 1 ) / ((1+1)*0xd) >= 1 ) ? chr( ( $_mp - 1 ) / 0x1a + ((1+1)*((1<<2)*0x8)) ) : '' ) . chr( ( $_mp - 1 ) % ((0x1e+0xd)-(0x15-0x4)) + 0x41 ) );
			$this->colnames[ $_mq ] = $_mp;
			$this->colindexes[ $_mp ]  = $_mq;
		}
		$this->store_extended_info = $_mn;
		if ( $_mm != "" ) {
			$this->read( $_mm );
		}
	}

	/**
	 * Set the encoding method
	 */
	function setOutputEncoding( $_mm ) {
		$this->_defaultEncoding = $_mm;
	}

	/**
	 *  $encoder = 'iconv' or 'mb'
	 *  set iconv if you would like use 'iconv' for encode UTF-16LE to your encoding
	 *  set mb if you would like use 'mb_convert_encoding' for encode UTF-16LE to your encoding
	 */
	function setUTFEncoder( $_mm = 'iconv' ) {
		$this->_encoderFunction = '';
		if ( $_mm == _greenweb_xd("\xc8\xd1\xac\xba\x93") ) {
			$this->_encoderFunction = function_exists( _greenweb_xd("\xc8\xd1\xac\xba\x93") ) ? _greenweb_xd("\xc8\xd1\xac\xba\x93") : '';
		} elseif ( $_mm == _greenweb_xd("\xcc\xd0") ) {
			$this->_encoderFunction = function_exists( _greenweb_xd("\xcc\xd0\x9c\xb7\x8a\x98\xd1\xdd\xbb\xa4\xbe\x97\xcd\xd7\xaa\xb2\xc8\xdc\xa4") ) ? _greenweb_xd("\xcc\xd0\x9c\xb7\x8a\x98\xd1\xdd\xbb\xa4\xbe\x97\xcd\xd7\xaa\xb2\xc8\xdc\xa4") : '';
		}
	}

	function setRowColOffset( $_mm ) {
		$this->_rowoffset = $_mm;
		$this->_coloffset = $_mm;
	}

	/**
	 * Set the default number format
	 */
	function setDefaultFormat( $_mm ) {
		$this->_defaultFormat = $_mm;
	}

	/**
	 * Force a column to use a certain format
	 */
	function setColumnFormat( $_mm, $_mn ) {
		$this->_columnsFormat[ $_mm ] = $_mn;
	}

	/**
	 * Read the spreadsheet file using OLE, then parse
	 */
	function read( $_mm ) {
		$_mn = $this->_ole->read( $_mm );

		// oops, something goes wrong (Darko Miljanovic)
		if ( $_mn === !1 ) {
			// check error code
			if ( $this->_ole->error == 1 ) {
				// bad file
				die( _greenweb_xd("\xf5\xda\xa6\xf4\x83\x9f\xcb\xdd\xa7\xb1\x8c\x97\x83") . $_mm . _greenweb_xd("\x81\xdb\xb0\xf4\x8b\x99\xd3\x98\xbb\xb5\x80\x96\xc2\xd6\xa9\xb3") );
			}
			// check other error codes here (eg bad fileformat, etc...)
		}
		$this->data = $this->_ole->getWorkBook();
		$this->_parse();
	}

	/**
	 * Parse a workbook
	 *
	 * @access private
	 * @return bool
	 */
	function _parse() {
		$_mm  = 0;
		$_mn = $this->data;

		$_mo          = v( $_mn, $_mm );
		$_mp        = v( $_mn, $_mm + 0x2 );
		$_mq       = v( $_mn, $_mm + ((1<<1)*0x2) );
		$_mr = v( $_mn, $_mm + 0x6 );

		$this->version = $_mq;

		if ( ( $_mq != SPREADSHEET_EXCEL_READER_BIFF8 ) &&
		     ( $_mq != SPREADSHEET_EXCEL_READER_BIFF7 )
		) {
			return !1;
		}

		if ( $_mr != SPREADSHEET_EXCEL_READER_WORKBOOKGLOBALS ) {
			return !1;
		}

		$_mm += $_mp + (0x2+(1+1));

		$_mo   = v( $_mn, $_mm );
		$_mp = v( $_mn, $_mm + 0x2 );

		while ( $_mo != SPREADSHEET_EXCEL_READER_TYPE_EOF ) {
			switch ( $_mo ) {
				case SPREADSHEET_EXCEL_READER_TYPE_SST:
					$_ms          = $_mm + 0x4;
					$_mt      = $_ms + $_mp;
					$_mu = $this->_GetInt4d( $_mn, $_ms + (((1+1)*0x3)-(1+1)) );
					$_ms          += 0x8;
					for ( $_mv = 0; $_mv < $_mu; $_mv ++ ) {
						// Read in the number of characters
						if ( $_ms == $_mt ) {
							$_mw    = v( $_mn, $_ms );
							$_mx = v( $_mn, $_ms + 0x2 );
							if ( $_mw != 0x3c ) {
								return - 1;
							}
							$_ms     += 0x4;
							$_mt = $_ms + $_mx;
						}
						$_my    = ord( $_mn[ $_ms ] ) | ( ord( $_mn[ $_ms + 1 ] ) << (1<<0x3) );
						$_ms        += (1<<1);
						$_mz = ord( $_mn[ $_ms ] );
						$_ms ++;
						$_n0  = ( ( $_mz & 0x01 ) == 0 );
						$_n1 = ( ( $_mz & 0x04 ) != 0 );

						// See if string contains formatting information
						$_n2 = ( ( $_mz & 0x08 ) != 0 );

						if ( $_n2 ) {
							// Read in the crun
							$_n3 = v( $_mn, $_ms );
							$_ms           += 0x2;
						}

						if ( $_n1 ) {
							// Read in cchExtRst
							$_n4 = $this->_GetInt4d( $_mn, $_ms );
							$_ms              += ((1<<1)*(1+1));
						}

						$_n5 = ( $_n0 ) ? $_my : $_my * (1<<1);
						if ( $_ms + $_n5 < $_mt ) {
							$_n6 = substr( $_mn, $_ms, $_n5 );
							$_ms   += $_n5;
						} else {
							// found countinue
							$_n6    = substr( $_mn, $_ms, $_mt - $_ms );
							$_n7 = $_mt - $_ms;
							$_n8 = $_my - ( ( $_n0 ) ? $_n7 : ( $_n7 / 0x2 ) );
							$_ms      = $_mt;

							while ( $_n8 > 0 ) {
								$_mw    = v( $_mn, $_ms );
								$_mx = v( $_mn, $_ms + 0x2 );
								if ( $_mw != 0x3c ) {
									return - 1;
								}
								$_ms     += (0x5-1);
								$_mt = $_ms + $_mx;
								$_n9   = ord( $_mn[ $_ms ] );
								$_ms     += 1;
								if ( $_n0 && ( $_n9 == 0 ) ) {
									$_n5           = min( $_n8, $_mt - $_ms ); // min($charsLeft, $conlength);
									$_n6        .= substr( $_mn, $_ms, $_n5 );
									$_n8     -= $_n5;
									$_n0 = !0;
								} elseif ( ! $_n0 && ( $_n9 != 0 ) ) {
									$_n5           = min( $_n8 * (1<<1), $_mt - $_ms ); // min($charsLeft, $conlength);
									$_n6        .= substr( $_mn, $_ms, $_n5 );
									$_n8     -= $_n5 / (1<<1);
									$_n0 = !1;
								} elseif ( ! $_n0 && ( $_n9 == 0 ) ) {
									// Bummer - the string starts off as Unicode, but after the
									// continuation it is in straightforward ASCII encoding
									$_n5 = min( $_n8, $_mt - $_ms ); // min($charsLeft, $conlength);
									for ( $_na = 0; $_na < $_n5; $_na ++ ) {
										$_n6 .= $_mn[ $_ms + $_na ] . chr( 0 );
									}
									$_n8     -= $_n5;
									$_n0 = !1;
								} else {
									$_nb = '';
									for ( $_na = 0; $_na < strlen( $_n6 ); $_na ++ ) {
										$_nb = $_n6[ $_na ] . chr( 0 );
									}
									$_n6        = $_nb;
									$_n5           = min( $_n8 * 0x2, $_mt - $_ms ); // min($charsLeft, $conlength);
									$_n6        .= substr( $_mn, $_ms, $_n5 );
									$_n8     -= $_n5 / (1<<1);
									$_n0 = !1;
								}
								$_ms += $_n5;
							}
						}
						$_n6 = ( $_n0 ) ? $_n6 : $this->_encodeUTF16( $_n6 );

						if ( $_n2 ) {
							$_ms += (0x3+1) * $_n3;
						}

						// For extended strings, skip over the extended string data
						if ( $_n1 ) {
							$_ms += $_n4;
						}
						$this->sst[] = $_n6;
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_FILEPASS:
					return !1;
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_NAME:
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_FORMAT:
					$_nc = v( $_mn, $_mm + (0x2*(1<<1)) );
					if ( $_mq == SPREADSHEET_EXCEL_READER_BIFF8 ) {
						$_nd = v( $_mn, $_mm + 0x6 );
						if ( ord( $_mn[ $_mm + 0x8 ] ) == 0 ) {
							$_ne = substr( $_mn, $_mm + (0x6+(0x2+1)), $_nd );
						} else {
							$_ne = substr( $_mn, $_mm + 0x9, $_nd * 0x2 );
						}
					} else {
						$_nd     = ord( $_mn[ $_mm + (0x5+1) ] );
						$_ne = substr( $_mn, $_mm + (0x9-0x2), $_nd * 0x2 );
					}
					$this->formatRecords[ $_nc ] = $_ne;
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_FONT:
					$_nf = v( $_mn, $_mm + 0x4 );
					$_n9 = v( $_mn, $_mm + (0x8-(1+1)) );
					$_ng  = v( $_mn, $_mm + (0x2*((1<<1)+(1<<1))) );
					$_nh = v( $_mn, $_mm + (((1<<1)*0x8)-(0x8-0x2)) );
					$_ni  = ord( $_mn[ $_mm + 0xe ] );
					$_nj   = "";
					// Font name
					$_nd = ord( $_mn[ $_mm + 0x12 ] );
					if ( ( ord( $_mn[ $_mm + 0x13 ] ) & 1 ) == 0 ) {
						$_nj = substr( $_mn, $_mm + (0x2*(0x2*(3+2))), $_nd );
					} else {
						$_nj = substr( $_mn, $_mm + 0x14, $_nd * 0x2 );
						$_nj = $this->_encodeUTF16( $_nj );
					}
					$this->fontRecords[] = array(
						_greenweb_xd("\xc9\xd7\xaa\xb3\x8d\x82") => $_nf / (0x4+(0x1d-0xd)),
						_greenweb_xd("\xc8\xc6\xa2\xb8\x8c\x95") => ! ! ( $_n9 & 0x2 ),
						_greenweb_xd("\xc2\xdd\xaf\xbb\x97")  => $_ng,
						_greenweb_xd("\xd4\xdc\xa7\xb1\x97")  => ! ( $_ni == 0 ),
						_greenweb_xd("\xc3\xdd\xaf\xb0")   => ( $_nh == (0xa*((98-9)-0x13)) ),
						_greenweb_xd("\xc7\xdd\xad\xa0")   => $_nj,
						_greenweb_xd("\xd3\xd3\xb4")    => $this->dumpHexData( $_mn, $_mm + (1+0x2), $_mp )
					);
					break;

				case SPREADSHEET_EXCEL_READER_TYPE_PALETTE:
					$_nk = ord( $_mn[ $_mm + 0x4 ] ) | ord( $_mn[ $_mm + 0x5 ] ) << 0x8;
					for ( $_nl = 0; $_nl < $_nk; $_nl ++ ) {
						$_nm                       = $_mm + 0x2 + ( $_nl * (((8-2)-1)-1) );
						$_nn                         = ord( $_mn[ $_nm ] );
						$_no                         = ord( $_mn[ $_nm + 1 ] );
						$_np                         = ord( $_mn[ $_nm + 0x2 ] );
						$this->colors[ 0x07 + $_nl ] = '#' . $this->myhex( $_nn ) . $this->myhex( $_no ) . $this->myhex( $_np );
					}
					break;

				case SPREADSHEET_EXCEL_READER_TYPE_XF:
					$_nq = ( ord( $_mn[ $_mm + (1<<2) ] ) | ord( $_mn[ $_mm + (0x8-3) ] ) << 0x8 ) - 1;
					$_nq = max( 0, $_nq );
					$_nc     = ord( $_mn[ $_mm + (0xa-4) ] ) | ord( $_mn[ $_mm + 0x7 ] ) << 0x8;
					$_nr      = ord( $_mn[ $_mm + 0xa ] ) & 0x3;
					$_ns           = ( ord( $_mn[ $_mm + 0x16 ] ) | ord( $_mn[ $_mm + 0x17 ] ) << (((1+1)*(1+2))+0x2) ) & 0x3FFF;
					$_nt       = ( $_ns & 0x7F );
//						$bgcolor = ($bgi & 0x3f80) >> 7;
					$_nu = "";
					if ( $_nr == (0x2+1) ) {
						$_nu = "right";
					}
					if ( $_nr == (1+1) ) {
						$_nu = "center";
					}

					$_nv = ( ord( $_mn[ $_mm + 0x15 ] ) & 0xFC ) >> (1<<1);
					if ( $_nv == 0 ) {
						$_nt = "";
					}

					$_nw                = array();
					$_nw[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xee\xd6\xad\xb5\x99")] = $_nc;
					$_nw[_greenweb_xd("\xc0\xde\xaa\xb3\x8b")]       = $_nu;
					$_nw[_greenweb_xd("\xc7\xdd\xad\xa0\xac\x98\xc3\xdd\xb1")]   = $_nq;
					$_nw[_greenweb_xd("\xc3\xd5\x80\xbb\x89\x99\xd5")]     = $_nt;
					$_nw[_greenweb_xd("\xc7\xdb\xaf\xb8\xb5\x97\xd3\xcc\xac\xa2\x8f")] = $_nv;

					$_nx             = ord( $_mn[ $_mm + (19-5) ] ) | ( ord( $_mn[ $_mm + (2+13) ] ) << 0x8 ) | ( ord( $_mn[ $_mm + 0x10 ] ) << (((4+9)-0x5)+0x8) ) | ( ord( $_mn[ $_mm + 0x11 ] ) << 0x18 );
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xeb\xdd\xaf\xa4")]   = $this->lineStyles[ ( $_nx & 0xF ) ];
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf5\xd1\xae\xb8\x95")]  = $this->lineStyles[ ( $_nx & 0xF0 ) >> (2+0x2) ];
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf3\xd7\xb9")]    = $this->lineStyles[ ( $_nx & 0xF00 ) >> (2*4) ];
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xe5\xd7\xbd\xa4\x8e\x9f")] = $this->lineStyles[ ( $_nx & 0xF000 ) >> (15-0x3) ];

					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xeb\xdd\xaf\xa4\xa2\x9d\xcf\xdb\xb7")]  = ( $_nx & 0x7F0000 ) >> (1<<(0x2+0x2));
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf5\xd1\xae\xb8\x95\xb1\xcc\xd8\xaa\xa4")] = ( $_nx & 0x3F800000 ) >> (((6-1)*(6+1))-((1<<1)+0xa));
					$_nx                 = ( ord( $_mn[ $_mm + (3*6) ] ) | ord( $_mn[ $_mm + (22-0x3) ] ) << 0x8 );

					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xf3\xd7\xb9\x93\x8e\x9e\xcc\xc6")]    = ( $_nx & 0x7F );
					$_nw[_greenweb_xd("\xc3\xdd\xb1\xb0\x80\x84\xe5\xd7\xbd\xa4\x8e\x9f\xe0\xdb\xa9\xb9\xd3")] = ( $_nx & 0x3F80 ) >> 0x7;

					if ( array_key_exists( $_nc, $this->dateFormats ) ) {
						$_nw[_greenweb_xd("\xd5\xcb\xb3\xb1")]   = _greenweb_xd("\xc5\xd3\xb7\xb1");
						$_nw[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")] = $this->dateFormats[ $_nc ];
						if ( $_nu == '' ) {
							$_nw[_greenweb_xd("\xc0\xde\xaa\xb3\x8b")] = _greenweb_xd("\xd3\xdb\xa4\xbc\x91");
						}
					} elseif ( array_key_exists( $_nc, $this->numberFormats ) ) {
						$_nw[_greenweb_xd("\xd5\xcb\xb3\xb1")]   = _greenweb_xd("\xcf\xc7\xae\xb6\x80\x84");
						$_nw[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")] = $this->numberFormats[ $_nc ];
						if ( $_nu == '' ) {
							$_nw[_greenweb_xd("\xc0\xde\xaa\xb3\x8b")] = _greenweb_xd("\xd3\xdb\xa4\xbc\x91");
						}
					} else {
						$_ny    = !1;
						$_nz = '';
						if ( $_nc > 0 ) {
							if ( isset( $this->formatRecords[ $_nc ] ) ) {
								$_nz = $this->formatRecords[ $_nc ];
							}
							if ( $_nz != "" ) {
								$_o0 = preg_replace( "/\;.*/", "", $_nz );
								$_o0 = preg_replace( "/^\[[^\]]*\]/", "", $_o0 );
								if ( preg_match( "/[^hmsday\/\-:\s\\\,AMP]/i", $_o0 ) == 0 ) { // found day and time format
									$_ny    = !0;
									$_nz = $_o0;
									$_nz = str_replace( array( _greenweb_xd("\xe0\xff\xec\x84\xa8"), _greenweb_xd("\xcc\xdf\xae\xb9"), _greenweb_xd("\xcc\xdf\xae") ), array(
										'a',
										'F',
										'M'
									), $_nz );
									// m/mm are used for both minutes and months - oh SNAP!
									// This mess tries to fix for that.
									// 'm' == minutes only if following h/hh or preceding s/ss
									$_nz = preg_replace( "/(h:?)mm?/", "$1i", $_nz );
									$_nz = preg_replace( "/mm?(:?s)/", "i$1", $_nz );
									// A single 'm' = n in PHP
									$_nz = preg_replace( "/(^|[^m])m([^m]|$)/", _greenweb_xd("\x85\x83\xad\xf0\xd7"), $_nz );
									$_nz = preg_replace( "/(^|[^m])m([^m]|$)/", _greenweb_xd("\x85\x83\xad\xf0\xd7"), $_nz );
									// else it's months
									$_nz = str_replace( _greenweb_xd("\xcc\xdf"), 'm', $_nz );
									// Convert single 'd' to 'j'
									$_nz = preg_replace( "/(^|[^d])d([^d]|$)/", _greenweb_xd("\x85\x83\xa9\xf0\xd7"), $_nz );
									$_nz = str_replace( array(
										_greenweb_xd("\xc5\xd6\xa7\xb0"),
										_greenweb_xd("\xc5\xd6\xa7"),
										_greenweb_xd("\xc5\xd6"),
										_greenweb_xd("\xd8\xcb\xba\xad"),
										_greenweb_xd("\xd8\xcb"),
										_greenweb_xd("\xc9\xda"),
										'h'
									), array( 'l', 'D', 'd', 'Y', 'y', 'H', 'g' ), $_nz );
									$_nz = preg_replace( "/ss?/", 's', $_nz );
								}
							}
						}
						if ( $_ny ) {
							$_nw[_greenweb_xd("\xd5\xcb\xb3\xb1")]   = _greenweb_xd("\xc5\xd3\xb7\xb1");
							$_nw[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")] = $_nz;
							if ( $_nu == '' ) {
								$_nw[_greenweb_xd("\xc0\xde\xaa\xb3\x8b")] = _greenweb_xd("\xd3\xdb\xa4\xbc\x91");
							}
						} else {
							// If the format string has a 0 or # in it, we'll assume it's a number
							if ( preg_match( "/[0#]/", $_nz ) ) {
								$_nw[_greenweb_xd("\xd5\xcb\xb3\xb1")] = _greenweb_xd("\xcf\xc7\xae\xb6\x80\x84");
								if ( $_nu == '' ) {
									$_nw[_greenweb_xd("\xc0\xde\xaa\xb3\x8b")] = _greenweb_xd("\xd3\xdb\xa4\xbc\x91");
								}
							} else {
								$_nw[_greenweb_xd("\xd5\xcb\xb3\xb1")] = _greenweb_xd("\xce\xc6\xab\xb1\x97");
							}
							$_nw[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")] = $_nz;
							$_nw[_greenweb_xd("\xc2\xdd\xa7\xb1")]   = $_nc;
						}
					}
					$this->xfRecords[] = $_nw;
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_NINETEENFOUR:
					$this->nineteenFour = ( ord( $_mn[ $_mm + 0x4 ] ) == 1 );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET:
					$_o1         = $this->_GetInt4d( $_mn, $_mm + ((0x3+0x2)-1) );
					$_o2       = ord( $_mn[ $_mm + 0x8 ] );
					$_o3 = ord( $_mn[ $_mm + 0x9 ] );
					$_o4         = ord( $_mn[ $_mm + (15-5) ] );

					if ( $_mq == SPREADSHEET_EXCEL_READER_BIFF8 ) {
						$_o5 = ord( $_mn[ $_mm + 0xb ] );
						if ( $_o5 == 0 ) {
							$_o6 = substr( $_mn, $_mm + 0xc, $_o4 );
						} else {
							$_o6 = $this->_encodeUTF16( substr( $_mn, $_mm + (((28-9)-0x3)-((7-1)-0x2)), $_o4 * 0x2 ) );
						}
					} elseif ( $_mq == SPREADSHEET_EXCEL_READER_BIFF7 ) {
						$_o6 = substr( $_mn, $_mm + 0xb, $_o4 );
					}
					$this->boundsheets[] = array( _greenweb_xd("\xcf\xd3\xae\xb1") => $_o6, _greenweb_xd("\xce\xd4\xa5\xa7\x80\x82") => $_o1 );
					break;

			}

			$_mm    += $_mp + (1<<0x2);
			$_mo   = ord( $_mn[ $_mm ] ) | ord( $_mn[ $_mm + 1 ] ) << (((8-2)-(1+1))+(1<<(1<<1)));
			$_mp = ord( $_mn[ $_mm + 0x2 ] ) | ord( $_mn[ $_mm + 0x3 ] ) << ((0x7+0x7)-0x6);
		}

		foreach ( $this->boundsheets as $_o7 => $_o8 ) {
			$this->sn = $_o7;
			$this->_parsesheet( $_o8[_greenweb_xd("\xce\xd4\xa5\xa7\x80\x82")] );
		}

		return !0;
	}

	/**
	 * Parse a worksheet
	 */
	function _parsesheet( $_mm ) {
		$_mn = !0;
		$_mo = $this->data;
		// read BOF
		$_mp   = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (1<<0x3);
		$_mq = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << (1<<0x3);

		$_mr       = ord( $_mo[ $_mm + 0x4 ] ) | ord( $_mo[ $_mm + (8-3) ] ) << 0x8;
		$_ms = ord( $_mo[ $_mm + (0x2*3) ] ) | ord( $_mo[ $_mm + (9-2) ] ) << ((0x12-0x5)-((2*3)-1));

		if ( ( $_mr != SPREADSHEET_EXCEL_READER_BIFF8 ) && ( $_mr != SPREADSHEET_EXCEL_READER_BIFF7 ) ) {
			return - 1;
		}

		if ( $_ms != SPREADSHEET_EXCEL_READER_WORKSHEET ) {
			return - 0x2;
		}
		$_mm += $_mq + (0x2*0x2);
		while ( $_mn ) {
			$_mt = ord( $_mo[ $_mm ] );
			if ( $_mt == SPREADSHEET_EXCEL_READER_TYPE_EOF ) {
				break;
			}
			$_mp                                = $_mt | ord( $_mo[ $_mm + 1 ] ) << ((0x2+(14-6))-0x2);
			$_mq                              = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << 0x8;
			$_mm                                += ((1+0x2)+1);
			$this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xa6\x8a\x81")] = $this->_rowoffset - 1;
			$this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xb7\x8a\x9a")] = $this->_coloffset - 1;
			unset( $this->rectype );
			switch ( $_mp ) {
				case SPREADSHEET_EXCEL_READER_TYPE_DIMENSION:
					if ( ! isset( $this->numRows ) ) {
						if ( ( $_mq == 0xa ) || ( $_mr == SPREADSHEET_EXCEL_READER_BIFF7 ) ) {
							$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x86\x8a\x81\xd4")] = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + (2+1) ] ) << (0x2*((1<<1)*0x2));
							$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x97\x8a\x9a\xd4")] = ord( $_mo[ $_mm + 0x6 ] ) | ord( $_mo[ $_mm + 0x7 ] ) << (((10-4)+(1+4))-0x3);
						} else {
							$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x86\x8a\x81\xd4")] = ord( $_mo[ $_mm + (1<<0x2) ] ) | ord( $_mo[ $_mm + (2+3) ] ) << (((1+1)*(2+1))+0x2);
							$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x97\x8a\x9a\xd4")] = ord( $_mo[ $_mm + (2*5) ] ) | ord( $_mo[ $_mm + (0xc-1) ] ) << 0x8;
						}
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_MERGEDCELLS:
					$_mu = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << 0x8;
					for ( $_mv = 0; $_mv < $_mu; $_mv ++ ) {
						$_mw = ord( $_mo[ $_mm + (1<<0x3) * $_mv + 2 ] ) | ord( $_mo[ $_mm + (1<<3) * $_mv + (2+1) ] ) << (((6+6)-0x3)-1);
						$_mx = ord( $_mo[ $_mm + 0x8 * $_mv + (1<<2) ] ) | ord( $_mo[ $_mm + 0x8 * $_mv + (0x6-1) ] ) << ((1<<1)*0x4);
						$_my = ord( $_mo[ $_mm + (2*4) * $_mv + 0x6 ] ) | ord( $_mo[ $_mm + (1<<0x3) * $_mv + (0x5+0x2) ] ) << 0x8;
						$_mz = ord( $_mo[ $_mm + (2*0x4) * $_mv + 0x8 ] ) | ord( $_mo[ $_mm + 0x8 * $_mv + (12-0x3) ] ) << (0x9-1);
						if ( $_mx - $_mw > 0 ) {
							$this->sheets[ $this->sn ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mw + 1 ][ $_my + 1 ][_greenweb_xd("\xd3\xdd\xb4\xa7\x95\x97\xc9")] = $_mx - $_mw + 1;
						}
						if ( $_mz - $_my > 0 ) {
							$this->sheets[ $this->sn ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mw + 1 ][ $_my + 1 ][_greenweb_xd("\xc2\xdd\xaf\xa7\x95\x97\xc9")] = $_mz - $_my + 1;
						}
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_RK:
				case SPREADSHEET_EXCEL_READER_TYPE_RK2:
					$_n0      = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << 0x8;
					$_n1   = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << 0x8;
					$_n2    = $this->_GetInt4d( $_mo, $_mm + 0x6 );
					$_n3 = $this->_GetIEEE754( $_n2 );
					$_n4     = $this->_getCellDetails( $_mm, $_n3, $_n1 );
					$this->addcell( $_n0, $_n1, $_n4[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")], $_n4 );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_LABELSST:
					$_n0     = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << ((0x2+(2*4))-(1<<1));
					$_n1  = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << (((2+19)-0x9)-(1<<0x2));
					$_n5 = ord( $_mo[ $_mm + (3+1) ] ) | ord( $_mo[ $_mm + (1+4) ] ) << 0x8;
					$_n6   = $this->_GetInt4d( $_mo, $_mm + (0x2+0x4) );
					$this->addcell( $_n0, $_n1, $this->sst[ $_n6 ], array( _greenweb_xd("\xd9\xd4\x8a\xba\x81\x93\xdf") => $_n5 ) );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_MULRK:
					$_n0      = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (1<<0x3);
					$_n7 = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << 0x8;
					$_n8  = ord( $_mo[ $_mm + $_mq - 0x2 ] ) | ord( $_mo[ $_mm + $_mq - 1 ] ) << (((12-4)-1)+1);
					$_n9  = $_n8 - $_n7 + 1;
					$_na   = $_mm + ((0x4+(1+1))-(1<<1));
					for ( $_mv = 0; $_mv < $_n9; $_mv ++ ) {
						$_n3 = $this->_GetIEEE754( $this->_GetInt4d( $_mo, $_na + 0x2 ) );
						$_n4     = $this->_getCellDetails( $_na - (0x2+0x2), $_n3, $_n7 + $_mv + 1 );
						$_na   += 0x6;
						$this->addcell( $_n0, $_n7 + $_mv, $_n4[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")], $_n4 );
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_NUMBER:
					$_n0    = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (0x2*(1<<0x2));
					$_n1 = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << (1<<0x3);
					$_nb    = unpack( "ddouble", substr( $_mo, $_mm + 0x6, 0x8 ) ); // It machine machine dependent
					if ( $this->isDate( $_mm ) ) {
						$_n3 = $_nb[_greenweb_xd("\xc5\xdd\xb6\xb6\x89\x93")];
					} else {
						$_n3 = $this->createNumber( $_mm );
					}
					$_n4 = $this->_getCellDetails( $_mm, $_n3, $_n1 );
					$this->addcell( $_n0, $_n1, $_n4[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")], $_n4 );
					break;

				case SPREADSHEET_EXCEL_READER_TYPE_FORMULA:
				case SPREADSHEET_EXCEL_READER_TYPE_FORMULA2:
					$_n0    = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (0xa-(1<<1));
					$_n1 = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + (2+1) ] ) << 0x8;
					if ( ( ord( $_mo[ $_mm + 0x6 ] ) == 0 ) && ( ord( $_mo[ $_mm + (22-10) ] ) == 0xff ) && ( ord( $_mo[ $_mm + (17-0x4) ] ) == 0xff ) ) {
						//String formula. Result follows in a STRING record
						// This row/col are stored to be referenced in that record
						// http://code.google.com/p/php-excel-reader/issues/detail?id=4
						$_nc = $_n0;
						$_nd = $_n1;
					} elseif ( ( ord( $_mo[ $_mm + 0x6 ] ) == 1 ) && ( ord( $_mo[ $_mm + 0xc ] ) == (((2*3)*(57-10))-(0x3*0x9)) ) && ( ord( $_mo[ $_mm + 0xd ] ) == (((1+2)*0x5)*(0x3+0xe)) ) ) {
						//Boolean formula. Result is in +2; 0=false,1=true
						// http://code.google.com/p/php-excel-reader/issues/detail?id=4
						if ( ord( $this->data[ $_mm + (10-0x2) ] ) == 1 ) {
							$this->addcell( $_n0, $_n1, "TRUE" );
						} else {
							$this->addcell( $_n0, $_n1, "FALSE" );
						}
					} elseif ( ( ord( $_mo[ $_mm + 0x6 ] ) == 0x2 ) && ( ord( $_mo[ $_mm + 0xc ] ) == ((0x3*(2*12))+0xb7) ) && ( ord( $_mo[ $_mm + 0xd ] ) == (0x120-(0x3*0xb)) ) ) {
						//Error formula. Error code is in +2;
					} elseif ( ( ord( $_mo[ $_mm + (4+0x2) ] ) == 0x3 ) && ( ord( $_mo[ $_mm + 0xc ] ) == ((0x8+(3+4))*0x11) ) && ( ord( $_mo[ $_mm + (19-0x6) ] ) == 0xff ) ) {
						//Formula result is a null string.
						$this->addcell( $_n0, $_n1, '' );
					} else {
						// result is a number, so first 14 bytes are just like a _NUMBER record
						$_nb = unpack( "ddouble", substr( $_mo, $_mm + ((1<<1)*0x3), 0x8 ) ); // It machine machine dependent
						if ( $this->isDate( $_mm ) ) {
							$_n3 = $_nb[_greenweb_xd("\xc5\xdd\xb6\xb6\x89\x93")];
						} else {
							$_n3 = $this->createNumber( $_mm );
						}
						$_n4 = $this->_getCellDetails( $_mm, $_n3, $_n1 );
						$this->addcell( $_n0, $_n1, $_n4[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")], $_n4 );
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_BOOLERR:
					$_n0    = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (1<<(0x2+1));
					$_n1 = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << 0x8;
					$_ne = ord( $_mo[ $_mm + 0x6 ] );
					$this->addcell( $_n0, $_n1, $_ne );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_STRING:
					// http://code.google.com/p/php-excel-reader/issues/detail?id=4
					if ( $_mr == SPREADSHEET_EXCEL_READER_BIFF8 ) {
						// Unicode 16 string, like an SST record
						$_nf        = $_mm;
						$_ng    = ord( $_mo[ $_nf ] ) | ( ord( $_mo[ $_nf + 1 ] ) << ((0x5+1)+0x2) );
						$_nf        += 0x2;
						$_nh = ord( $_mo[ $_nf ] );
						$_nf ++;
						$_ni  = ( ( $_nh & 0x01 ) == 0 );
						$_nj = ( ( $_nh & 0x04 ) != 0 );
						// See if string contains formatting information
						$_nk = ( ( $_nh & 0x08 ) != 0 );
						if ( $_nk ) {
							// Read in the crun
							$_nl = ord( $_mo[ $_nf ] ) | ( ord( $_mo[ $_nf + 1 ] ) << ((1+0x3)+(1<<0x2)) );
							$_nf           += (1+1);
						}
						if ( $_nj ) {
							// Read in cchExtRst
							$_nm = $this->_GetInt4d( $this->data, $_nf );
							$_nf              += ((0x7-1)-(1+1));
						}
						$_nn    = ( $_ni ) ? $_ng : $_ng * 0x2;
						$_no = substr( $_mo, $_nf, $_nn );
						$_nf   += $_nn;
						$_no = ( $_ni ) ? $_no : $this->_encodeUTF16( $_no );
					} elseif ( $_mr == SPREADSHEET_EXCEL_READER_BIFF7 ) {
						// Simple byte string
						$_nf     = $_mm;
						$_ng = ord( $_mo[ $_nf ] ) | ( ord( $_mo[ $_nf + 1 ] ) << (0x5+0x3) );
						$_nf     += (1+1);
						$_no   = substr( $_mo, $_nf, $_ng );
					}
					$this->addcell( $_nc, $_nd, $_no );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_ROW:
					$_n0     = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (((1<<2)+(6+1))-0x3);
					$_np = ord( $_mo[ $_mm + (0x3+0x3) ] ) | ( ( ord( $_mo[ $_mm + (8-1) ] ) << 0x8 ) & 0x7FFF );
					if ( ( $_np & 0x8000 ) > 0 ) {
						$_nq = - 1;
					} else {
						$_nq = $_np & 0x7FFF;
					}
					$_nr                              = ( ord( $_mo[ $_mm + (0x4+8) ] ) & 0x20 ) >> 0x5;
					$this->rowInfo[ $this->sn ][ $_n0 + 1 ] = Array(
						_greenweb_xd("\xc9\xd7\xaa\xb3\x8d\x82") => $_nq / 0x14,
						_greenweb_xd("\xc9\xdb\xa7\xb0\x80\x98") => $_nr
					);
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_DBCELL:
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_MULBLANK:
					$_n0    = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (0xd-((7-1)-1));
					$_n1 = ord( $_mo[ $_mm + 0x2 ] ) | ord( $_mo[ $_mm + (2+1) ] ) << (1<<(0x2+1));
					$_ns   = ( $_mq / 0x2 ) - 0x3;
					for ( $_nt = 0; $_nt < $_ns; $_nt ++ ) {
						$_n5 = ord( $_mo[ $_mm + 0x4 + ( $_nt * 2 ) ] ) | ord( $_mo[ $_mm + 0x5 + ( $_nt * 2 ) ] ) << (((1+1)+0x3)+((1<<1)+1));
						$this->addcell( $_n0, $_n1 + $_nt, "", array( _greenweb_xd("\xd9\xd4\x8a\xba\x81\x93\xdf") => $_n5 ) );
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_LABEL:
					$_n0    = ord( $_mo[ $_mm ] ) | ord( $_mo[ $_mm + 1 ] ) << (1+0x7);
					$_n1 = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << (1<<0x3);
					$this->addcell( $_n0, $_n1, substr( $_mo, $_mm + 0x8, ord( $_mo[ $_mm + (0x2*3) ] ) | ord( $_mo[ $_mm + 0x7 ] ) << 0x8 ) );
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_EOF:
					$_mn = !1;
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_HYPER:
					//  Only handle hyperlinks to a URL
					$_n0               = ord( $this->data[ $_mm ] ) | ord( $this->data[ $_mm + 1 ] ) << 0x8;
					$_nu              = ord( $this->data[ $_mm + 0x2 ] ) | ord( $this->data[ $_mm + 0x3 ] ) << (((4+3)+0x2)-1);
					$_n1            = ord( $this->data[ $_mm + (0x2*2) ] ) | ord( $this->data[ $_mm + (6-1) ] ) << ((1<<1)*(1<<0x2));
					$_nv           = ord( $this->data[ $_mm + 0x6 ] ) | ord( $this->data[ $_mm + 0x7 ] ) << 0x8;
					$_nw          = Array();
					$_nx             = ord( $this->data[ $_mm + 0x1c ] );
					$_ny             = "";
					$_nz             = "";
					$_o0              = (1<<((4+2)-1));
					$_nw[_greenweb_xd("\xc7\xde\xa2\xb3\x96")] = $_nx;
					if ( ( $_nx & 1 ) > 0 ) {   // is a type we understand
						//  is there a description ?
						if ( ( $_nx & 0x14 ) == 0x14 ) {   // has a description
							$_o0    += 0x4;
							$_o1 = ord( $this->data[ $_mm + (1<<5) ] ) | ord( $this->data[ $_mm + 0x21 ] ) << (0x2*((1+2)+1));
							$_ny   = substr( $this->data, $_mm + $_o0, $_o1 * 0x2 );
							$_o0    += (1<<1) * $_o1;
						}
						$_nz = $this->read16bitstring( $this->data, $_mm + $_o0 + (0x16-0x2) );
						if ( $_ny == "" ) {
							$_ny = $_nz;
						}
					}
					$_nw[_greenweb_xd("\xc5\xd7\xb0\xb7")] = $_ny;
					$_nw[_greenweb_xd("\xcd\xdb\xad\xbf")] = $this->_encodeUTF16( $_nz );
					for ( $_o2 = $_n0; $_o2 <= $_nu; $_o2 ++ ) {
						for ( $_nt = $_n1; $_nt <= $_nv; $_nt ++ ) {
							$this->sheets[ $this->sn ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_o2 + 1 ][ $_nt + 1 ][_greenweb_xd("\xc9\xcb\xb3\xb1\x97\x9a\xce\xd6\xa2")] = $_nw;
						}
					}
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_DEFCOLWIDTH:
					$this->defaultColWidth = ord( $_mo[ $_mm + (3+1) ] ) | ord( $_mo[ $_mm + (4+1) ] ) << (1<<(0x2+1));
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_STANDARDWIDTH:
					$this->standardColWidth = ord( $_mo[ $_mm + (0x3+1) ] ) | ord( $_mo[ $_mm + (4+1) ] ) << ((0x2*(1<<1))+0x4);
					break;
				case SPREADSHEET_EXCEL_READER_TYPE_COLINFO:
					$_o3 = ord( $_mo[ $_mm + 0 ] ) | ord( $_mo[ $_mm + 1 ] ) << (1<<(0x2+1));
					$_o4   = ord( $_mo[ $_mm + 2 ] ) | ord( $_mo[ $_mm + 0x3 ] ) << 0x8;
					$_o5      = ord( $_mo[ $_mm + 0x4 ] ) | ord( $_mo[ $_mm + 0x5 ] ) << 0x8;
					$_o6     = ord( $_mo[ $_mm + 0x6 ] ) | ord( $_mo[ $_mm + (11-4) ] ) << 0x8;
					$_o7      = ord( $_mo[ $_mm + (2*4) ] );
					for ( $_o8 = $_o3; $_o8 <= $_o4; $_o8 ++ ) {
						$this->colInfo[ $this->sn ][ $_o8 + 1 ] = Array(
							_greenweb_xd("\xd6\xdb\xa7\xa0\x8d")     => $_o5,
							_greenweb_xd("\xd9\xd4")        => $_o6,
							_greenweb_xd("\xc9\xdb\xa7\xb0\x80\x98")    => ( $_o7 & 0x01 ),
							_greenweb_xd("\xc2\xdd\xaf\xb8\x84\x86\xd4\xdd\xad") => ( $_o7 & 0x1000 ) >> (((4+18)-1)-((3+3)+0x3))
						);
					}
					break;

				default:
					break;
			}
			$_mm += $_mq;
		}

		if ( ! isset( $this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x86\x8a\x81\xd4")] ) ) {
			$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x86\x8a\x81\xd4")] = $this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xa6\x8a\x81")];
		}
		if ( ! isset( $this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x97\x8a\x9a\xd4")] ) ) {
			$this->sheets[ $this->sn ][_greenweb_xd("\xcf\xc7\xae\x97\x8a\x9a\xd4")] = $this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xb7\x8a\x9a")];
		}
	}

	function isDate( $_mm ) {
		$_mn = ord( $this->data[ $_mm + (6-0x2) ] ) | ord( $this->data[ $_mm + 0x5 ] ) << (1<<0x3);

		return ( $this->xfRecords[ $_mn ][_greenweb_xd("\xd5\xcb\xb3\xb1")] == _greenweb_xd("\xc5\xd3\xb7\xb1") );
	}

	// Get the details for a particular cell
	function _getCellDetails( $_mm, $_mn, $_mo ) {
		$_mp  = ord( $this->data[ $_mm + (1<<0x2) ] ) | ord( $this->data[ $_mm + (8-3) ] ) << 0x8;
		$_mq = $this->xfRecords[ $_mp ];
		$_mr     = $_mq[_greenweb_xd("\xd5\xcb\xb3\xb1")];

		$_ms      = $_mq[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")];
		$_mt = $_mq[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xee\xd6\xad\xb5\x99")];
		$_mu   = $_mq[_greenweb_xd("\xc7\xdd\xad\xa0\xac\x98\xc3\xdd\xb1")];
		$_mv = "";
		$_mw     = '';
		$_mx      = '';
		$_my         = '';

		if ( isset( $this->_columnsFormat[ $_mo + 1 ] ) ) {
			$_ms = $this->_columnsFormat[ $_mo + 1 ];
		}

		if ( $_mr == _greenweb_xd("\xc5\xd3\xb7\xb1") ) {
			// See http://groups.google.com/group/php-excel-reader-discuss/browse_frm/thread/9c3f9790d12d8e10/f2045c2369ac79de
			$_mw = _greenweb_xd("\xc5\xd3\xb7\xb1");
			// Convert numeric value into a date
			$_mz  = floor( $_mn - ( $this->nineteenFour ? SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS1904 : SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS ) );
			$_n0 = ( $_mz ) * SPREADSHEET_EXCEL_READER_MSINADAY;
			$_n1 = gmgetdate( $_n0 );

			$_my           = $_mn;
			$_n2 = $_mn - floor( $_mn ) + .0000001; // The .0000001 is to fix for php/excel fractional diffs

			$_n3 = floor( SPREADSHEET_EXCEL_READER_MSINADAY * $_n2 );
			$_n4         = $_n3 % (0x2*(0x5*(4+2)));
			$_n3 -= $_n4;
			$_n5        = floor( $_n3 / ( 0x3c * 0x3c ) );
			$_n6         = floor( $_n3 / ((0x60-0x1d)-(0x3+(1<<2))) ) % (((1+1)*0xd)+0x22);
			$_mx       = date( $_ms, mktime( $_n5, $_n6, $_n4, $_n1["mon"], $_n1["mday"], $_n1["year"] ) );
		} else if ( $_mr == _greenweb_xd("\xcf\xc7\xae\xb6\x80\x84") ) {
			$_mw     = _greenweb_xd("\xcf\xc7\xae\xb6\x80\x84");
			$_n7   = $this->_format_value( $_ms, $_mn, $_mt );
			$_mx      = $_n7[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")];
			$_mv = $_n7[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93")];
			$_my         = $_mn;
		} else {
			if ( $_ms == "" ) {
				$_ms = $this->_defaultFormat;
			}
			$_mw     = _greenweb_xd("\xd4\xdc\xa8\xba\x8a\x81\xc9");
			$_n7   = $this->_format_value( $_ms, $_mn, $_mt );
			$_mx      = $_n7[_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")];
			$_mv = $_n7[_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93")];
			$_my         = $_mn;
		}

		return array(
			_greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91")      => $_mx,
			_greenweb_xd("\xd3\xd3\xb4")         => $_my,
			_greenweb_xd("\xd3\xd7\xa0\xa0\x9c\x86\xc2")     => $_mw,
			_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82")      => $_ms,
			_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xee\xd6\xad\xb5\x99") => $_mt,
			_greenweb_xd("\xc7\xdd\xad\xa0\xac\x98\xc3\xdd\xb1")   => $_mu,
			_greenweb_xd("\xc7\xdd\xb1\xb9\x84\x82\xe4\xd7\xa5\xbf\x93") => $_mv,
			_greenweb_xd("\xd9\xd4\x8a\xba\x81\x93\xdf")     => $_mp
		);

	}

	function createNumber( $_mm ) {
		$_mn    = $this->_GetInt4d( $this->data, $_mm + ((1<<1)*((1+6)-(1+1))) );
		$_mo     = $this->_GetInt4d( $this->data, $_mm + (0x7-1) );
		$_mp         = ( $_mn & 0x80000000 ) >> 0x1f;
		$_mq          = ( $_mn & 0x7ff00000 ) >> 0x14;
		$_mr     = ( 0x100000 | ( $_mn & 0x000fffff ) );
		$_ms = ( $_mo & 0x80000000 ) >> ((1<<(1<<2))+(0x3*0x5));
		$_mt = ( $_mo & 0x7fffffff );
		$_mu        = $_mr / pow( (1<<1), ( 0x14 - ( $_mq - ((0x5*0x35)+((182-47)+0x26f)) ) ) );
		if ( $_ms != 0 ) {
			$_mu += 1 / pow( (1+1), ( 0x15 - ( $_mq - ((0x2*0x217)-((81-6)-(52-24))) ) ) );
		}
		$_mu += $_mt / pow( (1+1), ( 0x34 - ( $_mq - (0x1f*0x21) ) ) );
		if ( $_mp ) {
			$_mu = - 1 * $_mu;
		}

		return $_mu;
	}

	function addcell( $_mm, $_mn, $_mo, $_mp = null ) {
		$this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xa6\x8a\x81")]                                                        = max( $this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xa6\x8a\x81")], $_mm + $this->_rowoffset );
		$this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xb7\x8a\x9a")]                                                        = max( $this->sheets[ $this->sn ][_greenweb_xd("\xcc\xd3\xbb\xb7\x8a\x9a")], $_mn + $this->_coloffset );
		$this->sheets[ $this->sn ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96")][ $_mm + $this->_rowoffset ][ $_mn + $this->_coloffset ] = $_mo;
		if ( $this->store_extended_info && $_mp ) {
			foreach ( $_mp as $_mq => $_mr ) {
				$this->sheets[ $this->sn ][_greenweb_xd("\xc2\xd7\xaf\xb8\x96\xbf\xc9\xde\xa6")][ $_mm + $this->_rowoffset ][ $_mn + $this->_coloffset ][ $_mq ] = $_mr;
			}
		}
	}

	function _GetIEEE754( $_mm ) {
		if ( ( $_mm & 0x02 ) != 0 ) {
			$_mn = $_mm >> (1+1);
		} else {
			//mmp
			// I got my info on IEEE754 encoding from
			// http://research.microsoft.com/~hollasch/cgindex/coding/ieeefloat.html
			// The RK format calls for using only the most significant 30 bits of the
			// 64 bit floating point value. The other 34 bits are assumed to be 0
			// So, we use the upper 30 bits of $rknum as follows...
			$_mo     = ( $_mm & 0x80000000 ) >> 0x1f;
			$_mp      = ( $_mm & 0x7ff00000 ) >> 0x14;
			$_mq = ( 0x100000 | ( $_mm & 0x000ffffc ) );
			$_mn    = $_mq / pow( (1+1), ( (0xd+0x7) - ( $_mp - (0x3*0x155) ) ) );
			if ( $_mo ) {
				$_mn = - 1 * $_mn;
			}
			//end of changes by mmp
		}
		if ( ( $_mm & 0x01 ) != 0 ) {
			$_mn /= ((0x53-(32-9))+0x28);
		}

		return $_mn;
	}

	function _encodeUTF16( $_mm ) {
		$_mn = $_mm;
		if ( $this->_defaultEncoding ) {
			switch ( $this->_encoderFunction ) {
				case _greenweb_xd("\xc8\xd1\xac\xba\x93") :
					$_mn = iconv( _greenweb_xd("\xf4\xe6\x85\xf9\xd4\xc0\xeb\xfd"), $this->_defaultEncoding, $_mm );
					break;
				case _greenweb_xd("\xcc\xd0\x9c\xb7\x8a\x98\xd1\xdd\xbb\xa4\xbe\x97\xcd\xd7\xaa\xb2\xc8\xdc\xa4") :
					$_mn = mb_convert_encoding( $_mm, $this->_defaultEncoding, _greenweb_xd("\xf4\xe6\x85\xf9\xd4\xc0\xeb\xfd") );
					break;
			}
		}

		return $_mn;
	}

	function _GetInt4d( $_mm, $_mn ) {
		$_mo = ord( $_mm[ $_mn ] ) | ( ord( $_mm[ $_mn + 1 ] ) << 0x8 ) | ( ord( $_mm[ $_mn + 2 ] ) << 0x10 ) | ( ord( $_mm[ $_mn + 0x3 ] ) << (((2*3)-0x2)+((54-18)-(1<<4))) );
		if ( $_mo >= 0xfffffffe ) {
			$_mo = - 0x2;
		}

		return $_mo;
	}

}

?>
