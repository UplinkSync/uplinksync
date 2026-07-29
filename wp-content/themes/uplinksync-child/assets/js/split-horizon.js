/**
 * UplinkSync — "The Split Horizon" homepage motion (***).
 *
 * Ported from the approved prototype. Three enhancements, all progressive
 * (no-JS = the hero + sections render in their final, readable state):
 *   1. "Acquire uplink" load gesture — the seam draws in, the two nodes settle,
 *      the altitude ticks show, then the hero copy staggers in.
 *   2. Canvas ambient — a sparse, clean-technical constellation with a periodic
 *      uplink pulse travelling up the seam. RAF-paused when the hero is offscreen.
 *   3. Scroll reveals — IntersectionObserver adds .in to each band.
 *
 * prefers-reduced-motion: reduce  ⇒  everything jumps to its final state, no
 * animation and no RAF loop (a single static constellation frame is drawn).
 * Scoped to the .uls-splith homepage; a no-op on any page without it.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.uls-splith' );
	if ( ! root ) { return; }

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* ---------------- 1. acquire-uplink load gesture ---------------- */
	var hero     = root.querySelector( '.hero' );
	var seamLine = root.querySelector( '#seamLine' );
	var node1    = root.querySelector( '#node1' );
	var node2    = root.querySelector( '#node2' );
	var airTick  = root.querySelector( '.t-air' );
	var gndTick  = root.querySelector( '.t-gnd' );

	function acquireUplink() {
		if ( ! hero ) { return; }
		if ( reduce ) {
			if ( seamLine ) { seamLine.classList.add( 'drawn' ); }
			if ( node1 ) { node1.classList.add( 'settled' ); }
			if ( node2 ) { node2.classList.add( 'settled' ); }
			if ( airTick ) { airTick.classList.add( 'shown' ); }
			if ( gndTick ) { gndTick.classList.add( 'shown' ); }
			hero.classList.add( 'loaded' );
			return;
		}
		requestAnimationFrame( function () {
			requestAnimationFrame( function () { if ( seamLine ) { seamLine.classList.add( 'drawn' ); } } );
		} );
		setTimeout( function () { if ( node1 ) { node1.classList.add( 'settled' ); } }, 430 );
		setTimeout( function () { if ( node2 ) { node2.classList.add( 'settled' ); } }, 560 );
		setTimeout( function () { if ( airTick ) { airTick.classList.add( 'shown' ); } }, 560 );
		setTimeout( function () { hero.classList.add( 'loaded' ); }, 620 );
		setTimeout( function () { if ( gndTick ) { gndTick.classList.add( 'shown' ); } }, 660 );
	}
	if ( document.readyState === 'complete' ) { acquireUplink(); }
	else { window.addEventListener( 'load', acquireUplink ); }

	/* ---------------- 2. canvas ambient constellation ---------------- */
	var canvas = root.querySelector( '#field' );
	if ( canvas && canvas.getContext ) {
		var ctx = canvas.getContext( '2d' );
		var W = 0, H = 0, nodes = [], pulses = [], seamX = 0, lastPulse = 0, rafId = null;

		function sizeCanvas() {
			var host = canvas.parentNode;
			W = host.clientWidth; H = host.clientHeight;
			var dpr = Math.min( window.devicePixelRatio || 1, 2 );
			canvas.width = Math.round( W * dpr ); canvas.height = Math.round( H * dpr );
			canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
			ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
			seamX = W / 2;
		}
		function buildNodes() {
			nodes = [];
			var count = Math.max( 14, Math.min( 34, Math.round( ( W * H ) / 42000 ) ) );
			for ( var i = 0; i < count; i++ ) {
				nodes.push( {
					x: Math.random() * W, y: Math.random() * H,
					vx: ( Math.random() - .5 ) * .12, vy: ( Math.random() - .5 ) * .12,
					r: Math.random() * 1.4 + .6, a: Math.random() * .5 + .3
				} );
			}
		}
		function drawFrame( now ) {
			ctx.clearRect( 0, 0, W, H );
			for ( var i = 0; i < nodes.length; i++ ) {
				var n = nodes[ i ];
				n.x += n.vx; n.y += n.vy;
				if ( n.x < 0 || n.x > W ) { n.vx *= -1; } if ( n.y < 0 || n.y > H ) { n.vy *= -1; }
				for ( var j = i + 1; j < nodes.length; j++ ) {
					var m = nodes[ j ], dx = n.x - m.x, dy = n.y - m.y, d = Math.sqrt( dx * dx + dy * dy );
					if ( d < 130 ) {
						var op = ( 1 - d / 130 ) * .16;
						ctx.strokeStyle = 'rgba(149,213,221,' + op.toFixed( 3 ) + ')';
						ctx.lineWidth = 1;
						ctx.beginPath(); ctx.moveTo( n.x, n.y ); ctx.lineTo( m.x, m.y ); ctx.stroke();
					}
				}
				ctx.fillStyle = 'rgba(149,213,221,' + ( n.a * 0.55 ).toFixed( 3 ) + ')';
				ctx.beginPath(); ctx.arc( n.x, n.y, n.r, 0, Math.PI * 2 ); ctx.fill();
			}
			if ( now - lastPulse > 3400 ) { lastPulse = now; pulses.push( { y: H + 10 } ); }
			for ( var p = pulses.length - 1; p >= 0; p-- ) {
				var pu = pulses[ p ]; pu.y -= 2.4;
				if ( pu.y < -10 ) { pulses.splice( p, 1 ); continue; }
				var grad = ctx.createRadialGradient( seamX, pu.y, 0, seamX, pu.y, 26 );
				grad.addColorStop( 0, 'rgba(149,213,221,.5)' ); grad.addColorStop( 1, 'rgba(149,213,221,0)' );
				ctx.fillStyle = grad;
				ctx.beginPath(); ctx.arc( seamX, pu.y, 26, 0, Math.PI * 2 ); ctx.fill();
				ctx.fillStyle = 'rgba(220,245,248,0.9)';
				ctx.beginPath(); ctx.arc( seamX, pu.y, 1.6, 0, Math.PI * 2 ); ctx.fill();
			}
			rafId = requestAnimationFrame( drawFrame );
		}
		function staticFrame() {
			ctx.clearRect( 0, 0, W, H );
			for ( var i = 0; i < nodes.length; i++ ) {
				var n = nodes[ i ];
				ctx.fillStyle = 'rgba(149,213,221,' + ( n.a * 0.5 ).toFixed( 3 ) + ')';
				ctx.beginPath(); ctx.arc( n.x, n.y, n.r, 0, Math.PI * 2 ); ctx.fill();
			}
		}
		sizeCanvas(); buildNodes();
		if ( reduce ) { staticFrame(); }
		else { rafId = requestAnimationFrame( drawFrame ); }

		var resizeT;
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeT );
			resizeT = setTimeout( function () { sizeCanvas(); buildNodes(); if ( reduce ) { staticFrame(); } }, 180 );
		} );

		// Pause the RAF loop while the hero is offscreen.
		if ( ! reduce && 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( es ) {
				es.forEach( function ( e ) {
					if ( e.isIntersecting ) { if ( rafId === null ) { rafId = requestAnimationFrame( drawFrame ); } }
					else if ( rafId !== null ) { cancelAnimationFrame( rafId ); rafId = null; }
				} );
			}, { threshold: 0 } ).observe( hero );
		}
	}

	/* ---------------- 3. scroll reveals ---------------- */
	if ( 'IntersectionObserver' in window && ! reduce ) {
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) { if ( e.isIntersecting ) { e.target.classList.add( 'in' ); io.unobserve( e.target ); } } );
		}, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' } );
		root.querySelectorAll( '.band' ).forEach( function ( b ) { io.observe( b ); } );
	} else {
		root.querySelectorAll( '.band' ).forEach( function ( b ) { b.classList.add( 'in' ); } );
	}
}() );
