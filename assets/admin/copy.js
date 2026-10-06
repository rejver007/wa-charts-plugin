( () => {
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-wa-copy]' );
		if ( ! button || ! window.navigator.clipboard ) {
			return;
		}
		event.preventDefault();
		if ( ! button.dataset.waLabel ) {
			button.dataset.waLabel = button.textContent;
		}
		window.navigator.clipboard
			.writeText( button.getAttribute( 'data-wa-copy' ) )
			.then( () => {
				button.textContent = button.getAttribute( 'data-wa-copied' );
				window.setTimeout( () => {
					button.textContent = button.dataset.waLabel;
				}, 1500 );
			} );
	} );
} )();
