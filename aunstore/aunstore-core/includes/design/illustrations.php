<?php
/**
 * Vector scenes for the homepage "Shop by space" cards — one per category, drawn as a family:
 * a screen on the far wall, a projector in the foreground throwing a beam at it, and the people using it.
 *
 *   home      a living room at night: a movie on the big screen, two viewers on the sofa
 *   office    a meeting room: charts on the screen, a presenter, colleagues at the table
 *   portable  a backyard movie night: a hanging screen, string lights, trees, a tent, stars
 *
 * Each SVG fills its card (preserveAspectRatio "slice"). The subject sits in the top ~60% of the
 * 400×460 canvas because the card's title and button cover the bottom. Small motions (beam, stars,
 * chart bars) are driven by the .aunx-il-* classes in home-css.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_room_svg( $scene ) {
	$open = '<svg class="aunx-room-art" viewBox="0 0 400 460" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';

	// The projector on its stand + the beam, shared by all three scenes (p = id prefix, y = lens height).
	$projector = function ( $p ) {
		return '<polygon class="aunx-il-beam" points="200,220 62,192 338,192" fill="url(#' . $p . '-beam)"/>'
			. '<rect x="181" y="218" width="38" height="17" rx="5" fill="#1b2d52" stroke="#6fb6ff" stroke-opacity=".7"/>'
			. '<circle cx="200" cy="220" r="4" fill="#dff1ff"/><circle cx="200" cy="220" r="9" fill="#9fd2ff" opacity=".35"/>'
			. '<rect x="187" y="227" width="9" height="2.5" rx="1" fill="#6fb6ff" opacity=".7"/><circle cx="211" cy="228" r="1.6" fill="#ffbc00"/>';
	};
	$beam = function ( $p ) {
		return '<linearGradient id="' . $p . '-beam" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#cfe9ff" stop-opacity=".6"/><stop offset="1" stop-color="#cfe9ff" stop-opacity=".05"/></linearGradient>';
	};

	switch ( $scene ) {

		case 'home':
			return $open . '<defs>'
				. '<linearGradient id="h-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#13275a"/><stop offset=".55" stop-color="#0b1730"/><stop offset="1" stop-color="#060b16"/></linearGradient>'
				. '<linearGradient id="h-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a1a63"/><stop offset=".42" stop-color="#b83b7f"/><stop offset=".78" stop-color="#ff8f4a"/><stop offset="1" stop-color="#ffd27a"/></linearGradient>'
				. '<radialGradient id="h-glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ff8f6a" stop-opacity=".5"/><stop offset="1" stop-color="#ff8f6a" stop-opacity="0"/></radialGradient>'
				. '<clipPath id="h-clip"><rect x="62" y="34" width="276" height="156" rx="8"/></clipPath>'
				. $beam( 'h' ) . '</defs>'
				. '<rect width="400" height="460" fill="url(#h-wall)"/>'
				. '<ellipse cx="200" cy="112" rx="250" ry="150" fill="url(#h-glow)"/>'
				// the movie on screen: a sunset over mountains
				. '<g clip-path="url(#h-clip)">'
				. '<rect x="62" y="34" width="276" height="156" fill="url(#h-sky)"/>'
				. '<circle class="aunx-il-sun" cx="200" cy="146" r="27" fill="#fff4cf"/>'
				. '<path d="M62 190V150l42-38 34 30 38-46 44 52 32-24 46 38 40-28v46z" fill="#4a2269" opacity=".92"/>'
				. '<path d="M62 190v-22l58-26 48 24 58-27 66 31 46-16v36z" fill="#1f1340"/>'
				. '<path d="M112 66q6-7 12 0q6-7 12 0M256 84q5-6 10 0q5-6 10 0M300 60q4-5 8 0q4-5 8 0" fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="1.6" stroke-linecap="round"/>'
				. '</g>'
				. '<rect x="62" y="34" width="276" height="156" rx="8" fill="none" stroke="#fff" stroke-opacity=".28" stroke-width="1.5"/>'
				// a floor lamp and a plant either side
				. '<path d="M30 300V206" stroke="#31466f" stroke-width="3" stroke-linecap="round"/><path d="M16 206h28l-7-24H23z" fill="#ffcf8a" opacity=".85"/><ellipse cx="30" cy="214" rx="26" ry="18" fill="#ffcf8a" opacity=".12"/>'
				. '<path d="M370 300v-40" stroke="#1d3a3a" stroke-width="3"/><path d="M370 262c-16-8-22-26-20-40 14 4 22 18 20 40zM370 262c16-8 22-26 20-40-14 4-22 18-20 40zM370 250c-4-14-2-30 0-38 4 8 6 24 0 38z" fill="#1f6b5c"/>'
				. $projector( 'h' )
				// two viewers on the sofa, lit from the screen
				. '<rect x="54" y="256" width="292" height="216" rx="26" fill="#060b16"/>'
				. '<circle cx="126" cy="246" r="17" fill="#060b16" stroke="#ff9f7a" stroke-opacity=".55" stroke-width="1.5"/>'
				. '<circle cx="274" cy="244" r="18" fill="#060b16" stroke="#ff9f7a" stroke-opacity=".55" stroke-width="1.5"/>'
				. '<path d="M54 282q0-26 26-26h240q26 0 26 26" fill="none" stroke="#2a3f66" stroke-width="1.5"/>'
				. '</svg>';

		case 'office':
			return $open . '<defs>'
				. '<linearGradient id="o-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#16326b"/><stop offset=".55" stop-color="#0c1a36"/><stop offset="1" stop-color="#060b16"/></linearGradient>'
				. '<radialGradient id="o-glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#7cc4ff" stop-opacity=".42"/><stop offset="1" stop-color="#7cc4ff" stop-opacity="0"/></radialGradient>'
				. $beam( 'o' ) . '</defs>'
				. '<rect width="400" height="460" fill="url(#o-wall)"/>'
				// window blinds hint + wall clock
				. '<g opacity=".22" stroke="#9fd2ff" stroke-width="1"><path d="M8 40h36M8 52h36M8 64h36M8 76h36M8 88h36M8 100h36"/></g>'
				. '<circle cx="372" cy="48" r="13" fill="none" stroke="#9fd2ff" stroke-opacity=".5" stroke-width="1.5"/><path d="M372 48v-7M372 48l5 3" stroke="#9fd2ff" stroke-opacity=".7" stroke-width="1.5" stroke-linecap="round"/>'
				. '<ellipse cx="200" cy="112" rx="240" ry="140" fill="url(#o-glow)"/>'
				// the slide: bar chart + trend line + donut
				. '<rect x="62" y="34" width="276" height="156" rx="8" fill="#f3f8ff"/>'
				. '<rect x="80" y="50" width="92" height="9" rx="4.5" fill="#0f172a" opacity=".82"/><rect x="80" y="65" width="58" height="5" rx="2.5" fill="#0f172a" opacity=".3"/>'
				. '<path d="M80 172h128" stroke="#c6d6ea" stroke-width="1.5"/>'
				. '<rect class="aunx-il-bar" style="--i:0" x="86" y="134" width="17" height="38" rx="3" fill="#9fd2ff"/>'
				. '<rect class="aunx-il-bar" style="--i:1" x="110" y="118" width="17" height="54" rx="3" fill="#4da8ff"/>'
				. '<rect class="aunx-il-bar" style="--i:2" x="134" y="126" width="17" height="46" rx="3" fill="#9fd2ff"/>'
				. '<rect class="aunx-il-bar" style="--i:3" x="158" y="100" width="17" height="72" rx="3" fill="#0188fe"/>'
				. '<rect class="aunx-il-bar" style="--i:4" x="182" y="84" width="17" height="88" rx="3" fill="#0166c8"/>'
				. '<path d="M94 126l24-14 24 8 24-26 24-16" fill="none" stroke="#ffbc00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="190" cy="78" r="4" fill="#ffbc00"/>'
				. '<g transform="rotate(-90 276 108)" fill="none" stroke-width="14"><circle cx="276" cy="108" r="30" stroke="#dbe8f8"/><circle cx="276" cy="108" r="30" stroke="#0188fe" stroke-dasharray="113 189"/><circle cx="276" cy="108" r="30" stroke="#ffbc00" stroke-dasharray="38 189" stroke-dashoffset="-113"/></g>'
				. '<rect x="246" y="158" width="26" height="5" rx="2.5" fill="#0188fe"/><rect x="278" y="158" width="26" height="5" rx="2.5" fill="#ffbc00"/><rect x="246" y="169" width="40" height="5" rx="2.5" fill="#0f172a" opacity=".25"/>'
				. '<rect x="62" y="34" width="276" height="156" rx="8" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="1.5"/>'
				// presenter pointing at the chart
				. '<circle cx="364" cy="150" r="12" fill="#0a1428" stroke="#7cc4ff" stroke-opacity=".6" stroke-width="1.5"/>'
				. '<path d="M346 262v-70q0-24 18-24t18 24v70z" fill="#0a1428" stroke="#7cc4ff" stroke-opacity=".45" stroke-width="1.5"/>'
				. '<path d="M350 184l-26-30" stroke="#0a1428" stroke-width="9" stroke-linecap="round"/><path d="M350 184l-26-30" stroke="#7cc4ff" stroke-opacity=".5" stroke-width="1.5" stroke-linecap="round"/><path d="M324 154l-22-18" stroke="#ffbc00" stroke-width="2.5" stroke-linecap="round"/>'
				. $projector( 'o' )
				// colleagues at the table, a laptop open
				. '<rect x="28" y="258" width="344" height="216" rx="18" fill="#060b16"/><path d="M28 280q0-22 18-22h308q18 0 18 22" fill="none" stroke="#2a3f66" stroke-width="1.5"/>'
				. '<circle cx="84" cy="246" r="15" fill="#060b16" stroke="#7cc4ff" stroke-opacity=".5" stroke-width="1.5"/>'
				. '<circle cx="136" cy="249" r="15" fill="#060b16" stroke="#7cc4ff" stroke-opacity=".5" stroke-width="1.5"/>'
				. '<circle cx="264" cy="249" r="15" fill="#060b16" stroke="#7cc4ff" stroke-opacity=".5" stroke-width="1.5"/>'
				. '<circle cx="312" cy="246" r="15" fill="#060b16" stroke="#7cc4ff" stroke-opacity=".5" stroke-width="1.5"/>'
				. '</svg>';

		default: // portable
			$stars = '';
			foreach ( array( array( 24, 30, 1.6 ), array( 58, 96, 1.2 ), array( 36, 150, 1.4 ), array( 330, 120, 1.3 ), array( 372, 84, 1.6 ), array( 352, 170, 1.1 ), array( 196, 16, 1.3 ), array( 120, 22, 1.1 ), array( 270, 26, 1.5 ), array( 384, 28, 1.2 ), array( 14, 78, 1.1 ), array( 300, 46, 1 ) ) as $n => $s ) {
				$stars .= '<circle class="aunx-il-star" style="--i:' . $n . '" cx="' . $s[0] . '" cy="' . $s[1] . '" r="' . $s[2] . '" fill="#fff"/>';
			}
			$bulbs = '';
			foreach ( array( array( 20, 44 ), array( 58, 52 ), array( 344, 50 ), array( 380, 42 ) ) as $n => $b ) {
				$bulbs .= '<circle cx="' . $b[0] . '" cy="' . $b[1] . '" r="3.4" fill="#ffd27a"/><circle cx="' . $b[0] . '" cy="' . $b[1] . '" r="8" fill="#ffd27a" opacity=".2"/>';
			}
			return $open . '<defs>'
				. '<linearGradient id="p-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0a1233"/><stop offset=".5" stop-color="#1b2f72"/><stop offset=".72" stop-color="#0d1b3c"/><stop offset="1" stop-color="#060b16"/></linearGradient>'
				. '<linearGradient id="p-film" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#00c6ff"/><stop offset=".55" stop-color="#6d5cff"/><stop offset="1" stop-color="#ff5e87"/></linearGradient>'
				. '<radialGradient id="p-glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#7a8bff" stop-opacity=".45"/><stop offset="1" stop-color="#7a8bff" stop-opacity="0"/></radialGradient>'
				. '<clipPath id="p-clip"><rect x="84" y="60" width="232" height="130" rx="6"/></clipPath>'
				. $beam( 'p' ) . '</defs>'
				. '<rect width="400" height="460" fill="url(#p-sky)"/>'
				. $stars
				// crescent moon
				. '<path d="M318 8a16 16 0 1 0 14 24a13 13 0 0 1-14-24z" fill="#fff4cf"/>'
				// string lights between the trees
				. '<path d="M0 36q70 26 100 22M300 58q40 6 100-22" fill="none" stroke="#6b7fb3" stroke-width="1.2"/>' . $bulbs
				. '<ellipse cx="200" cy="126" rx="220" ry="120" fill="url(#p-glow)"/>'
				// pine trees
				. '<path d="M22 300v-70M22 170l-26 46h16l-18 34h56l-18-34h16z" fill="#0b2a33" stroke="#0b2a33" stroke-width="4" stroke-linejoin="round"/>'
				. '<path d="M378 300v-60M378 186l-24 42h15l-17 32h52l-17-32h15z" fill="#0b2a33" stroke="#0b2a33" stroke-width="4" stroke-linejoin="round"/>'
				// the hanging screen, tied off with ropes
				. '<path d="M84 60L66 46M316 60l18-14M84 190l-14 16M316 190l14 16" stroke="#9fb3d8" stroke-width="1.5" stroke-linecap="round"/>'
				. '<g clip-path="url(#p-clip)"><rect x="84" y="60" width="232" height="130" fill="url(#p-film)"/>'
				. '<circle cx="262" cy="98" r="17" fill="#fff" opacity=".9"/>'
				. '<path d="M84 190v-34l40-30 34 26 40-42 46 46 30-22 42 30v26z" fill="#1d1a5c" opacity=".78"/>'
				. '<path d="M84 190v-16l52-20 50 20 60-24 70 28v12z" fill="#0f0d3a"/></g>'
				. '<rect x="84" y="60" width="232" height="130" rx="6" fill="none" stroke="#fff" stroke-opacity=".4" stroke-width="1.5"/>'
				// tent on the left, backpack on the right
				. '<path d="M64 268l34-50 34 50z" fill="#ff7a4d"/><path d="M98 218l12 50H86z" fill="#0a1428" opacity=".55"/><path d="M98 218v-10" stroke="#ff7a4d" stroke-width="2" stroke-linecap="round"/>'
				. '<rect x="304" y="238" width="26" height="30" rx="8" fill="#0188fe"/><path d="M310 238q7-14 14 0" fill="none" stroke="#0188fe" stroke-width="3"/><rect x="309" y="250" width="16" height="9" rx="3" fill="#0a1428" opacity=".35"/>'
				// projector on a little tripod
				. '<path d="M200 236l-12 30M200 236l12 30M200 236v30" stroke="#6fb6ff" stroke-opacity=".7" stroke-width="2" stroke-linecap="round"/>'
				. '<polygon class="aunx-il-beam" points="200,220 86,190 314,190" fill="url(#p-beam)"/>'
				. '<rect x="184" y="218" width="32" height="17" rx="5" fill="#1b2d52" stroke="#6fb6ff" stroke-opacity=".8"/><circle cx="200" cy="220" r="4" fill="#dff1ff"/><circle cx="200" cy="220" r="9" fill="#9fd2ff" opacity=".35"/>'
				// two friends on a blanket
				. '<path d="M40 268q160-16 320 0v200H40z" fill="#060b16"/>'
				. '<circle cx="150" cy="252" r="14" fill="#060b16" stroke="#9aa6ff" stroke-opacity=".55" stroke-width="1.5"/>'
				. '<circle cx="250" cy="250" r="15" fill="#060b16" stroke="#9aa6ff" stroke-opacity=".55" stroke-width="1.5"/>'
				. '</svg>';
	}
}
