console.log( 'siusk24 block-checkout' );
let siusk_terminal_select                          = '';
let siusk_terminal_previously_selected             = '';
let siusk_terminal_previously_selected_description = '';

let siusk24_terminals = {};

function siusk24_get_shipping_method_block() {
	let data                = {};
	let shipping_block_html = jQuery( '.wc-block-components-shipping-rates-control' );
	if (typeof shipping_block_html != 'undefined' && shipping_block_html !== null) {
		let shipping_radio_buttons = jQuery( shipping_block_html ).find( 'input[name^="radio-control-"]' );
		if ( shipping_radio_buttons.length > 0 ) {
			let method                  = jQuery( 'input[name^="radio-control-"]:checked' ).val();
			let ship_method_instance_id = '';
			if ('undefined' == typeof method || null === method) {
				method = jQuery( 'input[name^="radio-control-"]' ).val();
			}

			if (typeof method != 'undefined' && method !== null) {
				if (method.indexOf( ':' ) > -1) {
					let arr                 = method.split( ':' );
					method                  = arr[0];
					ship_method_instance_id = arr[1];
				}
			}
			data.method      = method;
			data.instance_id = ship_method_instance_id;
		}
	}

	return data;
}


function siusk24_change_react_input_value(input,value) {

	if (typeof input != 'undefined' && input !== null) {
		var nativeInputValueSetter = Object.getOwnPropertyDescriptor(
			window.HTMLInputElement.prototype,
			"value"
		).set;
		nativeInputValueSetter.call( input, value );

		var inputEvent = new Event( "input", {bubbles: true} );
		input.dispatchEvent( inputEvent );
	}
}




function siusk24_create_validation_modal() {
	// Create main modal container.
	const modal = document.createElement( 'div' );
	modal.id    = 'siusk24_checkout_validation_modal';
	Object.assign(
		modal.style,
		{
			display: 'none',
			position: 'fixed',
			top: '0',
			left: '0',
			width: '100%',
			height: '100%',
			backgroundColor: 'rgba(0, 0, 0, 0.5)',
			justifyContent: 'center',
			alignItems: 'center',
			zIndex: '1000'
		}
	);

	// Create modal content container.
	const modalContent = document.createElement( 'div' );
	Object.assign(
		modalContent.style,
		{
			backgroundColor: 'white',
			width: '90%',
			maxWidth: '300px',
			padding: '20px',
			position: 'relative',
			textAlign: 'center',
			borderRadius: '10px',
			boxShadow: '0px 4px 10px rgba(0, 0, 0, 0.1)'
		}
	);

	// Create close button (×).
	const closeSpan       = document.createElement( 'span' );
	closeSpan.id          = 'siusk24_close_modal_cross';
	closeSpan.textContent = '×';
	Object.assign(
		closeSpan.style,
		{
			position: 'absolute',
			top: '10px',
			right: '15px',
			fontSize: '20px',
			cursor: 'pointer'
		}
	);

	// Create message div.
	const messageDiv     = document.createElement( 'div' );
	messageDiv.innerHTML = 'Siusk 24<br>Parcel terminal must be chosen.';
	Object.assign(
		messageDiv.style,
		{
			margin: '20px 0',
			fontSize: '18px'
		}
	);

	// Create OK button.
	const okButton       = document.createElement( 'button' );
	okButton.id          = 'siusk24_close_modal_button';
	okButton.textContent = 'Ok';
	Object.assign(
		okButton.style,
		{
			padding: '10px 20px',
			backgroundColor: '#FFA900',
			color: 'white',
			border: 'none',
			borderRadius: '5px',
			cursor: 'pointer',
			fontSize: '16px'
		}
	);

	// Assemble the modal.
	modalContent.appendChild( closeSpan );
	modalContent.appendChild( messageDiv );
	modalContent.appendChild( okButton );
	modal.appendChild( modalContent );

	return modal;
}

document.addEventListener(
	'DOMContentLoaded',
	function () {

		let validation_modal = siusk24_create_validation_modal();
		// Append modal to body.
		document.body.appendChild( validation_modal );

		// Event Listeners for closing modal.
		let modal_close_1 = document.getElementById( 'siusk24_close_modal_cross' );
		if (modal_close_1) {
			modal_close_1.addEventListener( 'click', siusk24_close_validation_modal );
		}
		let modal_close_2 = document.getElementById( 'siusk24_close_modal_button' );
		if (modal_close_2) {
			modal_close_2.addEventListener( 'click', siusk24_close_validation_modal );
		}

		setTimeout(
			function () {

				// Assuming wcSettings is globally available
				if (typeof 'undefined' != wcSettings && null !== wcSettings ) {
					if ( 'siusk_24_block_data' in wcSettings ) {
						if ( 'terminals' in wcSettings.siusk_24_block_data ) {
							siusk24_terminals = wcSettings.siusk_24_block_data.terminals;
						}
					}
				}

				siusk_terminal_select = siusk24_render_terminal_select( siusk24_terminals );

				let shipping_block_html = document.querySelector( '.wc-block-components-shipping-rates-control' );

				if (shipping_block_html) {
					// Check if any radio buttons exist inside the block
					let shipping_radio_buttons = shipping_block_html.querySelectorAll( 'input[name^="radio-control-"]' );

					if (shipping_radio_buttons.length > 0) {
						// Find the currently checked input
						let selected_shipping_input = document.querySelector( 'input[name^="radio-control-"]:checked' );

						let shipping_option_id = selected_shipping_input.value;

						if ( shipping_option_id.indexOf( 'siusk24_service_' ) !== -1 ) {
							let siusk_service_id = shipping_option_id.split( "siusk24_service_" )[1];
							console.log( 'siusk_service_id on load: ' + siusk_service_id ); // "S30"
							siusk24_change_react_input_value( document.getElementById( 'siusk_24_service' ), siusk_service_id );
						} else {
							siusk24_change_react_input_value( document.getElementById( 'siusk_24_service' ), '' );
						}

						if (shipping_option_id.indexOf( 'siusk24_terminal_' ) !== -1) {
							//console.log('on load option siusk24_terminal_');

							let current_shipping_method_layout = selected_shipping_input.nextElementSibling;

							siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal_shipping_method' ), shipping_option_id );

							// Ensure the sibling exists and matches the class.
							if (current_shipping_method_layout &&
							current_shipping_method_layout.classList.contains( 'wc-block-components-radio-control__option-layout' )) {

								if (typeof siusk_terminal_select !== 'undefined') {

									const layoutRect = current_shipping_method_layout.getBoundingClientRect();

									const tempDiv     = document.createElement( 'div' );
									tempDiv.innerHTML = siusk_terminal_select;
									const newElement  = tempDiv.firstElementChild;

									// Only proceed if newElement hasn't been inserted yet.
									// (Checking if our unique wrapper already exists to prevent duplicates on re-runs).
									if (newElement && ! document.getElementById( 'siusk24_generated_wrapper' )) {

										// 3. Create a Wrapper DIV.
										const wrapper           = document.createElement( 'div' );
										wrapper.id              = 'siusk24_generated_wrapper';
										wrapper.style.marginTop = '15px';
										wrapper.style.width     = layoutRect.width + 'px';

										// 4. Setup and Append the Select element.
										newElement.style.width = '100%';
										wrapper.appendChild( newElement );

										// 5. Append the Map Container and Hidden Input.
										wrapper.insertAdjacentHTML( 'beforeend', '<div id="siusk24_map_container"></div><input type="hidden" id="siusk24_terminal_hidden"/>' );

										// 6. Insert the Wrapper into the DOM.
										current_shipping_method_layout.insertAdjacentElement( 'afterend', wrapper );
										console.log( 'on load insertAdjacentElement' );

										// 7. Initialize Select2 (if available).
										if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
											const $select = jQuery( newElement );

											$select.select2(
												{
													/*width: '100%'*/
												}
											);

											$select.on(
												'select2:select',
												function (e) {
													const data  = e.params.data;
													const value = data.id;
													const text  = data.text;

													console.log( 'select2:select' );
													console.log( value );
													console.log( text );

													siusk_terminal_previously_selected             = value;
													siusk_terminal_previously_selected_description = text;

													if (typeof siusk24_change_react_input_value === 'function') {
														siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
													}

													// Update the text display.
													const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
													if (displaySpan) {
														displaySpan.innerHTML = text;
														displaySpan.classList.add( 'show-selected' );
													}

												}
											);
										} else {

											jQuery( document ).on(
												'change',
												"select[name='siusk24_terminal']",
												function () {
													var value                                      = jQuery( this ).val();
													var text                                       = jQuery( this ).find( 'option:selected' ).text();
													siusk_terminal_previously_selected             = value;
													siusk_terminal_previously_selected_description = text;
													if (typeof siusk24_change_react_input_value === 'function') {
														siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
													}
													const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
													if (displaySpan) {
														displaySpan.innerHTML = text;

														displaySpan.classList.add( 'show-selected' );
													}

													console.log( "Value:", value );
													console.log( "Text:", text );
												}
											);
										}

										console.log(
											'%c loadSiusk24Map (on checkout initial load) ',
											'color: blue; font-weight: bold; font-size: 14px;'
										);
										loadSiusk24MappingBlock();
									}
								}
							}
						} else {
							console.log( 'remove select' );

							// Logic to remove the whole wrapper.
							// This removes the select, the map, and the hidden input all at once.
							const wrapper = document.getElementById( 'siusk24_generated_wrapper' );
							if (wrapper) {
								wrapper.remove();
							}
						}
					}
				}
			},
			1200
		);
	}
);


document.addEventListener(
	'click',
	function (e) {
		e          = e || window.event;
		var target = e.target || e.srcElement;

		if ( target.hasAttribute( 'id' ) ) {
			if (target.getAttribute( 'id' ) === 'testtesttest' ) {
				e.preventDefault();
			}
		}

		if ( target.closest( '.wc-block-components-checkout-place-order-button' ) || target.classList.contains( 'wc-block-components-checkout-place-order-button' )
			|| target.classList.contains( 'wc-block-checkout__actions_row' ) ) {

			let reactjs_input       = document.getElementById( 'siusk24_terminal' );
			let reactjs_input_value = false;
			let is_locker_required  = false;

			let shipping_block_html = document.querySelector( '.wc-block-components-shipping-rates-control' );

			if (shipping_block_html) {
				// Check if any radio buttons exist inside the block.
				let shipping_radio_buttons = shipping_block_html.querySelectorAll( 'input[name^="radio-control-"]' );

				if (shipping_radio_buttons.length > 0) {
					// Find the currently checked input.
					let selected_shipping_input = document.querySelector( 'input[name^="radio-control-"]:checked' );
					let shipping_option_id      = selected_shipping_input.value;
					if (shipping_option_id.indexOf( 'siusk24_terminal_' ) !== -1) {
						is_locker_required = true;
					}
				}
			}

			if (typeof reactjs_input != 'undefined' && reactjs_input !== null) {
				reactjs_input_value = reactjs_input.value;
			}

			if ( is_locker_required && ! reactjs_input_value ) {
				siusk24_open_validation_modal();
			}
		}
	}
);


function siusk24_open_validation_modal() {
	document.getElementById( 'siusk24_checkout_validation_modal' ).style.display = 'flex';
}

function siusk24_close_validation_modal() {
	document.getElementById( 'siusk24_checkout_validation_modal' ).style.display = 'none';

	// Scroll to map button.
	let scrollToElement = document.getElementById( 'easypack_block_type_geowidget' );

	if (scrollToElement) {
		scrollToElement.scrollIntoView( {behavior: 'smooth' } );
	}

}



function siusk24_get_configured_methods() {
	if (typeof wcSettings != 'undefined' && wcSettings !== null) {
		if (wcSettings.inpost_pl_block_data && wcSettings.inpost_pl_block_data.configured_methods) {
			return wcSettings.inpost_pl_block_data.configured_methods;
		}
	}
	return [];
}



jQuery( document.body ).on(
	'update_checkout',
	function () {
		//console.log('Siusk 24: blocks updated_checkout action');
	}
);



// Debounce helper.
function siusk24_debounce(func, wait) {
	let timeout;
	return function executedFunction() {
		var args    = arguments;
		var context = this;
		var later   = function () {
			clearTimeout( timeout );
			func.apply( context, args );
		};
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
	};
}

// Function to check for the element.
function siusk24_check_js_mode_select() {

	const element = document.getElementById( 'siusk24_generated_wrapper' );

	if ( ! element) {
		//console.log('easypack button DOES NOT exists');

		let shipping_data = siusk24_get_shipping_method_block();
		let method        = shipping_data.method;
		let instance_id   = shipping_data.instance_id;

		let shipping_block_html = document.querySelector( '.wc-block-components-shipping-rates-control' );

		if (shipping_block_html) {
			// Check if any radio buttons exist inside the block.
			let shipping_radio_buttons = shipping_block_html.querySelectorAll( 'input[name^="radio-control-"]' );

			if (shipping_radio_buttons.length > 0) {
				// Find the currently checked input.
				let selected_shipping_input = document.querySelector( 'input[name^="radio-control-"]:checked' );

				let shipping_option_id = selected_shipping_input.value;

				if (shipping_option_id.indexOf( 'siusk24_terminal_' ) !== -1) {
					console.log( 'on load option siusk24_terminal_' );
					siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal_shipping_method' ), shipping_option_id );

					let current_shipping_method_layout = selected_shipping_input.nextElementSibling;

					// Ensure the sibling exists and matches the class.
					if (current_shipping_method_layout &&
						current_shipping_method_layout.classList.contains( 'wc-block-components-radio-control__option-layout' )) {

						if (typeof siusk_terminal_select !== 'undefined') {

							if ( '' !== siusk_terminal_previously_selected ) {
								console.log( 'Terminal_previously_selected: ' + siusk_terminal_previously_selected );

								//siusk_terminal_select = siusk24_render_terminal_select( siusk24_terminals, siusk_terminal_previously_selected );
								//console.log('re-render select');
								if (typeof siusk24_change_react_input_value === 'function') {
									siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), siusk_terminal_previously_selected );
								}
							}

							// 1. Get the computed width of the reference element.
							const layoutRect = current_shipping_method_layout.getBoundingClientRect();

							// 2. Create a temporary container to turn the HTML string into a real DOM element.
							const tempDiv     = document.createElement( 'div' );
							tempDiv.innerHTML = siusk_terminal_select;
							const newElement  = tempDiv.firstElementChild;

							// Only proceed if newElement hasn't been inserted yet.
							// (Checking if our unique wrapper already exists to prevent duplicates on re-runs).
							if (newElement && ! document.getElementById( 'siusk24_generated_wrapper' )) {

								// 3. Create a Wrapper DIV.
								const wrapper           = document.createElement( 'div' );
								wrapper.id              = 'siusk24_generated_wrapper';
								wrapper.style.marginTop = '15px';
								wrapper.style.width     = layoutRect.width + 'px';

								// 4. Setup and Append the Select element.
								newElement.style.width = '100%';
								wrapper.appendChild( newElement );

								// 5. Append the Map Container and Hidden Input.
								wrapper.insertAdjacentHTML( 'beforeend', '<div id="siusk24_map_container"></div><input type="hidden" id="siusk24_terminal_hidden"/>' );

								// 6. Insert the Wrapper into the DOM.
								current_shipping_method_layout.insertAdjacentElement( 'afterend', wrapper );
								console.log( 'on load insertAdjacentElement' );

								// 7. Initialize Select2 (if available).
								if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
									const $select = jQuery( newElement );

									$select.select2(
										{
											/*width: '100%'*/
										}
									);

									if ( '' !== siusk_terminal_previously_selected_description ) {
										console.log( 'Terminal_previously_selected: ' + siusk_terminal_previously_selected_description );
										let displaySpan = document.querySelector( '.tmjs-selected-terminal' );
										if (displaySpan && siusk_terminal_previously_selected_description) {
											displaySpan.innerHTML = siusk_terminal_previously_selected_description;
											displaySpan.classList.add( 'show-selected' );
										} else {
											console.log( 'No displaySpan' );
											siusk24_wait_for_element( '.tmjs-selected-terminal' ).then(
												function (displaySpan) {
													// console.log( "map_button_is_visible" );
													if (siusk_terminal_previously_selected_description) {
														displaySpan.innerHTML = siusk_terminal_previously_selected_description;
														displaySpan.classList.add( 'show-selected' );
													}
												}
											);
										}

										jQuery( "select[name='siusk24_terminal']" ).each(
											function (i, select) {
												var $select = jQuery( select );

												// 1. SET THE VALUE
												// Check if option exists in the list
												if ($select.find( "option[value='" + siusk_terminal_previously_selected + "']" ).length) {
													// Option exists: select it and trigger change
													$select.val( siusk_terminal_previously_selected ).trigger( 'change' );
												} else {
													// Option does not exist: create it, append it, then select it
													var newOption = new Option( siusk_terminal_previously_selected_description, siusk_terminal_previously_selected, true, true );
													$select.append( newOption ).trigger( 'change' );
												}

												// 2. FIX THE STYLING (The fix)
												// The Select2 container is the NEXT sibling of the original select, not a child.
												$select.next( '.select2-container' ).find( '.select2-selection--single' ).css( 'background', '#c5f1b0' );

											}
										);
									}

									$select.on(
										'select2:select',
										function (e) {
											const data  = e.params.data;
											const value = data.id;
											const text  = data.text;

											console.log( 'select2:changed' );
											console.log( value );
											console.log( text );

											siusk_terminal_previously_selected = value;

											if (typeof siusk24_change_react_input_value === 'function') {
												siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
											}

											// Update the text display.
											let displaySpan = document.querySelector( '.tmjs-selected-terminal' );
											if (displaySpan) {
												displaySpan.innerHTML = text;

												displaySpan.classList.add( 'show-selected' );
											}

										}
									);
								} else {

									jQuery( document ).on(
										'change',
										"select[name='siusk24_terminal']",
										function () {
											var value                                      = jQuery( this ).val();
											var text                                       = jQuery( this ).find( 'option:selected' ).text();
											siusk_terminal_previously_selected             = value;
											siusk_terminal_previously_selected_description = text;
											if (typeof siusk24_change_react_input_value === 'function') {
												siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
											}
											const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
											if (displaySpan) {
												displaySpan.innerHTML = text;

												displaySpan.classList.add( 'show-selected' );
											}

											console.log( "Value:", value );
											console.log( "Text:", text );
										}
									);
								}

								console.log(
									'%c loadSiusk24Map (on checkout refresh) ',
									'color: blue; font-weight: bold; font-size: 14px;'
								);
								loadSiusk24MappingBlock();
							}
						}
					}
				}
			}
		}

	} else {
		//console.log('siusk24_generated_wrapper exists');

	}
}

// Debounced version (waits 300ms after last change).
const siusk24_debounced_check = siusk24_debounce( siusk24_check_js_mode_select, 300 );

// Initial check.
siusk24_check_js_mode_select();

// Watch for DOM mutations.
const observer = new MutationObserver( siusk24_debounced_check );

// Start observing.
const siusk24_start_observing = function () {
	if (document.body) {
		observer.observe(
			document.body,
			{
				childList: true,
				subtree: true
			}
		);
	}
};

if (document.readyState === 'loading') {
	document.addEventListener( 'DOMContentLoaded', siusk24_start_observing );
} else {
	siusk24_start_observing();
}

document.addEventListener(
	'change',
	function (e) {
		e          = e || window.event;
		var target = e.target || e.srcElement;

		// Check if the changed element is the specific radio input
		if (target.classList.contains( 'wc-block-components-radio-control__input' )) {
			const parent = document.getElementById( "shipping-option" );

			// Ensure the target is inside the shipping-option container
			if (parent && parent.contains( target )) {
				console.log( "Shipping Option Selected" );

				// Assuming this function exists globally.
				if (typeof siusk24_change_react_input_value === 'function') {
					siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), '' );
				}

				let shipping_option_id = target.getAttribute( 'id' );
				if (shipping_option_id) {
					console.log( 'shipping_option_id' );
					console.log( shipping_option_id );

					if (shipping_option_id.includes( "siusk24_service_" )) {
						let siusk_service_id = shipping_option_id.split( "siusk24_service_" )[1];
						console.log( siusk_service_id ); // "S30"
						siusk24_change_react_input_value( document.getElementById( 'siusk_24_service' ), siusk_service_id );
					} else {
						siusk24_change_react_input_value( document.getElementById( 'siusk_24_service' ), '' );
					}

					if (shipping_option_id.indexOf( 'siusk24_terminal_' ) !== -1) {

						siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal_shipping_method' ), shipping_option_id );

						// 1. Get the immediate next sibling.
						let current_shipping_method_layout = target.nextElementSibling;

						// 2. Check if that sibling exists and has the specific class.
						if (current_shipping_method_layout &&
						current_shipping_method_layout.classList.contains( 'wc-block-components-radio-control__option-layout' )) {

							// This inserts the HTML string strictly *after* the layout element.
							if (typeof siusk_terminal_select !== 'undefined') {
								// 1. Get the computed width of the reference element.
								const layoutRect = current_shipping_method_layout.getBoundingClientRect();

								if ( siusk_terminal_previously_selected ) {
									siusk_terminal_select = siusk24_render_terminal_select( siusk24_terminals, siusk_terminal_previously_selected );
									console.log( 're-render select' );
									if (typeof siusk24_change_react_input_value === 'function') {
										siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), siusk_terminal_previously_selected );
									}

									// Update the text display.
									const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
									if (displaySpan && siusk_terminal_previously_selected_description) {
										displaySpan.innerHTML = siusk_terminal_previously_selected_description;
										displaySpan.classList.add( 'show-selected' );
									} else {
										console.log( 'No displaySpan' );
										siusk24_wait_for_element( '.tmjs-selected-terminal' ).then(
											function (displaySpan) {
												// console.log( "map_button_is_visible" );
												if (siusk_terminal_previously_selected_description) {
													displaySpan.innerHTML = siusk_terminal_previously_selected_description;
													displaySpan.classList.add( 'show-selected' );
												}
											}
										);
									}
								}

								// 2. Create a temporary container to turn the HTML string into a real DOM element.
								const tempDiv     = document.createElement( 'div' );
								tempDiv.innerHTML = siusk_terminal_select;
								const newElement  = tempDiv.firstElementChild; // Gets the actual <select> or wrapper div.

								if (newElement) {
									// 1. Create a Wrapper DIV to hold everything (Select + Map + Input).
									const wrapper           = document.createElement( 'div' );
									wrapper.id              = 'siusk24_generated_wrapper'; // Unique ID for easy removal.
									wrapper.style.marginTop = '15px';
									wrapper.style.width     = layoutRect.width + 'px'; // Set width from parent layout.

									// 2. Setup and Append the Select element.
									newElement.style.width = '100%'; // Ensure select fills the wrapper.
									wrapper.appendChild( newElement );

									// 3. Append the Map Container and Hidden Input (New Addition).
									wrapper.insertAdjacentHTML( 'beforeend', '<div id="siusk24_map_container"></div><input type="hidden" id="siusk24_terminal_hidden"/>' );

									// 4. Insert the Wrapper into the DOM.
									current_shipping_method_layout.insertAdjacentElement( 'afterend', wrapper );

									// 5. Initialize Select2 (if available).
									if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
										const $select = jQuery( newElement );

										$select.select2(
											{
												/*width: '100%'*/
											}
										);

										$select.on(
											'select2:select',
											function (e) {
												const data  = e.params.data;
												const value = data.id;
												const text  = data.text;

												console.log( 'select2:changed' );
												console.log( value );
												console.log( text );

												siusk_terminal_previously_selected = value;

												if (typeof siusk24_change_react_input_value === 'function') {
													siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
												}

												// Update the text display
												const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
												if (displaySpan) {
													displaySpan.innerHTML = text;
													displaySpan.classList.add( 'show-selected' );
												} else {
													console.log( 'No displaySpan' );
													siusk24_wait_for_element( '.tmjs-selected-terminal' ).then(
														function (displaySpan) {
															// console.log( "map_button_is_visible" );
															if (siusk_terminal_previously_selected_description) {
																displaySpan.innerHTML = siusk_terminal_previously_selected_description;
																displaySpan.classList.add( 'show-selected' );
															}
														}
													);
												}

											}
										);
									} else {
										jQuery( document ).on(
											'change',
											"select[name='siusk24_terminal']",
											function () {
												var value                                      = jQuery( this ).val();
												var text                                       = jQuery( this ).find( 'option:selected' ).text();
												siusk_terminal_previously_selected             = value;
												siusk_terminal_previously_selected_description = text;
												if (typeof siusk24_change_react_input_value === 'function') {
													siusk24_change_react_input_value( document.getElementById( 'siusk24_terminal' ), value );
												}
												const displaySpan = document.querySelector( '.tmjs-selected-terminal' );
												if (displaySpan) {
													displaySpan.innerHTML = text;
													displaySpan.classList.add( 'show-selected' );
												} else {
													console.log( 'No displaySpan' );
													siusk24_wait_for_element( '.tmjs-selected-terminal' ).then(
														function (displaySpan) {
															// console.log( "map_button_is_visible" );
															if (siusk_terminal_previously_selected_description) {
																displaySpan.innerHTML = siusk_terminal_previously_selected_description;
																displaySpan.classList.add( 'show-selected' );
															}
														}
													);
												}

												console.log( "Value:", value );
												console.log( "Text:", text );
											}
										);
									}

									console.log(
										'%c loadSiusk24Map (shipping input changed) ',
										'color: blue; font-weight: bold; font-size: 14px;'
									);
									loadSiusk24MappingBlock();
									removeSiusk24MappingBlock();

								}
							}
						}
					} else {
						console.log( 'remove select' );
						// Logic to remove the select box if needed goes here.
						jQuery( '.siusk24_terminal' ).each(
							function (ind, elem) {
								jQuery( elem ).remove();
							}
						);

						// Logic to remove the whole wrapper.
						// Since we wrapped everything in #siusk24_generated_wrapper, we just kill that.
						const wrapper = document.getElementById( 'siusk24_generated_wrapper' );
						if (wrapper) {
							wrapper.remove();
						}
					}
				}
			}
		}
	}
);


function siusk24_render_terminal_select( terminals, selected_id = '' ) {
	// If no terminals or not an array, return empty select.
	if ( ! Array.isArray( terminals ) || terminals.length === 0) {
		return '<select class="siusk24_terminal" name="siusk24_terminal"></select>';
	}

	// Group terminals by city.
	const groupedOptions = {};

	terminals.forEach(
		function (terminal) {
			var city       = terminal.city;
			var id         = terminal.id;
			var optionText = terminal.name + ', ' + terminal.address;

			if ( ! groupedOptions[city]) {
				groupedOptions[city] = [];
			}

			groupedOptions[city].push(
				{
					id: id,
					text: optionText
				}
			);
		}
	);

	let parcelTerminals = '';
	let counter         = 0;

	// Build optgroups and options.
	for (const city in groupedOptions) {
		if (groupedOptions.hasOwnProperty( city )) {
			const locations = groupedOptions[city];

			// Start optgroup.
			parcelTerminals += `<optgroup data-id="${counter}" label="${city}">`;

			// Add options.
			locations.forEach(
				function (location) {
					var isSelected   = (location.id === selected_id) ? 'selected' : '';
					parcelTerminals += '<option value="' + location.id + '" ' + isSelected + '>' + location.text + '</option>';
				}
			);

			// Close optgroup.
			parcelTerminals += '</optgroup>';
			counter++;
		}
	}

	// Return complete select element.
	return `<select class="siusk24_terminal" name="siusk24_terminal">${parcelTerminals}</select>`;
}



function loadSiusk24MappingBlock() {

	//console.log(siusk24_block);

	siusk24_terminals_loading = true;
	let isModalReady          = false;
	var tmjs                  = new Siusk24Mapping( siusk24_block.api_url + '/api/v1' );

	tmjs
		.sub(
			'terminal-selected',
			function (data) {
				jQuery( 'input[name="order[receiver_attributes][parcel_machine_id]"]' ).val( data.id );
				jQuery( '#order_receiver_attributes_terminal_address' ).val( data.name + ", " + data.address );
				jQuery( '.receiver_parcel_machine_address_filled' ).text( '' );
				jQuery( '.receiver_parcel_machine_address_filled' ).append(
					'<div class="d-inline-flex" style="margin-top: 5px;">' +
					'<img class="my-auto mx-0 me-2" src="' + siusk24_block.api_url + '/default_icon_icon.svg" width="25" height="25">' +
					'<h5 class="my-auto mx-0">' + data.address + ", " + data.zip + ", " + data.city + '</h5></div>' +
					'<br><a class="select_parcel_btn select_parcel_href" data-remote="true" href="#">Pakeisti</a>'
				)
				jQuery( '.receiver_parcel_machine_address_filled' ).show();
				jQuery( '.receiver_parcel_machine_address_notfilled' ).hide();

				tmjs.publish( 'close-map-modal' );
			}
		);

	//tmjs_country_code = jQuery('#order_receiver_attributes_country_code').val();
	//tmjs_identifier = jQuery('#order_receiver_attributes_service_identifier').val();

	let country = '';
	//wcSettings.siusk_24_block_data.script_country
	if (typeof 'undefined' != wcSettings && null !== wcSettings ) {
		if ( 'siusk_24_block_data' in wcSettings ) {
			if ( 'script_country' in wcSettings.siusk_24_block_data ) {
				country = wcSettings.siusk_24_block_data.script_country;
			}
		}
	}

	console.log( 'siusk24_country' );
	console.log( country );

	tmjs.setImagesPath( siusk24data.images_path );
	tmjs.init( {country_code: country , identifier: '', city: siusk24_getCityBlock() , postal_code: '', receiver_address: '', max_distance: siusk24_block.max_distance} );

	window['tmjs'] = tmjs;

	tmjs.setTranslation(
		{
			modal_header: siusk24data.text_map,
			terminal_list_header: siusk24data.text_list,
			seach_header: siusk24data.text_search,
			search_btn: siusk24data.text_search,
			modal_open_btn: siusk24data.text_select_terminal,
			geolocation_btn: siusk24data.text_my_loc,
			your_position: 'Distance calculated from this point',
			nothing_found: siusk24data.text_not_found,
			no_cities_found: siusk24data.text_no_city,
			geolocation_not_supported: 'Geolocation not supported',

			// Unused strings
			search_placeholder: siusk24data.text_enter_address,
			workhours_header: 'Work hours',
			contacts_header: 'Contacts',
			select_pickup_point: '',
			no_pickup_points: 'No terminal',
			select_btn: siusk24data.text_select,
			back_to_list_btn: siusk24data.text_reset,
			no_information: siusk24data.text_not_found
		}
	);

	tmjs.sub(
		'tmjs-ready',
		function (t) {
			t.map.ZOOM_SELECTED = 8;
			isModalReady        = true;
			jQuery( '.spinner-border' ).hide();
			jQuery( '.select_parcel_btn' ).removeClass( 'disabled' ).html( siusk24data.text_select_terminal );
			siusk24_terminals_loading = false;

			var selected_postcode = siusk24_getPostcode();
			t.dom.searchNearest( selected_postcode );
			t.dom.UI.modal.querySelector( '.tmjs-search-input' ).value = selected_postcode;
		}
	);

	jQuery( document ).on(
		'click',
		'.select_parcel_btn',
		function (e) {
			e.preventDefault();
			if ( ! isModalReady) {
				return;
			}
			tmjs.publish( 'open-map-modal' );
			coords = {lng: jQuery( '.receiver_coords' ).attr( 'value-x' ), lat: jQuery( '.receiver_coords' ).attr( 'value-y' )};
			if (coords != undefined) {
				tmjs.map.addReferencePosition( coords );
				tmjs.dom.renderTerminalList( tmjs.map.addDistance( coords ), true )
			}
		}
	);

}


function removeSiusk24MappingBlock() {
	if ( typeof tmjs !== 'undefined' && siusk24_terminals_loading === false ) {
		var container = document.getElementById( tmjs.containerId );
		if ( document.body.contains( container ) ) {
			container.remove();
		}

		var modal = document.getElementById( tmjs.containerId + "_modal" );
		if ( document.body.contains( modal ) ) {
			modal.remove();
		}

		window['tmjs'] = null;
	}
}

function siusk24_getCityBlock() {
	var city;
	if (jQuery( '#ship-to-different-address-checkbox' ).length && jQuery( '#ship-to-different-address-checkbox' ).is( ':checked' )) {
		if (jQuery( "#shipping_city" ).length && $( "#shipping_city" ).val()) {
			city = jQuery( "#shipping_city" ).val();
		}
	} else {
		if (jQuery( "#billing_city" ).length && jQuery( "#billing_city" ).val()) {
			city = jQuery( "#billing_city" ).val();
		} else if (jQuery( "#calc_shipping_city" ).length && jQuery( "#calc_shipping_city" ).val()) {
			city = jQuery( "#calc_shipping_city" ).val();
		}
	}
	return city;
}


function siusk24_wait_for_element(selector) {
	return new Promise(
		function (resolve) {
			if (document.querySelector( selector )) {
				return resolve( document.querySelector( selector ) );
			}

			const observer = new MutationObserver(
				function (mutations) {
					if (document.querySelector( selector )) {
						resolve( document.querySelector( selector ) );
						observer.disconnect();
					}
				}
			);

			observer.observe(
				document.body,
				{
					childList: true,
					subtree: true
				}
			);
		}
	);
}
