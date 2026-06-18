import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
const { ExperimentalOrderMeta } = wc.blocksCheckout;

export const Block = ( { checkoutExtensionData, extensions } ) => {

    let isSiuskShippingMethod     = false;
    let selectedShippingInstanceID = null;

    const [siuskTerminalID, setsiuskTerminalID]         = useState( "" );
    const [siuskTerminalMethodID, setsiuskTerminalMethodID]         = useState( "" );
    const [siusk24ShippingService, setSiusk24ShippingService] = useState( "" );
    const { setExtensionData }                                  = checkoutExtensionData;
    const validationErrorId                               = 'siusk24-terminal-id-error';
    const { setValidationErrors, clearValidationError }         = useDispatch(
        'wc/store/validation'
    );

    // Get current shipping options.
    let shippingRates = useSelect(
        function (select) {
            const store = select( 'wc/store/cart' );
            return store.getShippingRates();
        }
    );

    // State to track pickup selection with both store and DOM monitoring.
    const [isPickupSelected, setIsPickupSelected] = useState(false);

    // Get pickup selection status from store.
    const storePickupSelected = useSelect(
        (select) => {
            const checkoutStore = select('wc/store/checkout');
            const prefersCollection = checkoutStore.prefersCollection();
            return prefersCollection;
        },
        [] // Dependencies array - will re-run when store changes.
    );

    // Update local state when store changes.
    useEffect(() => {
        setIsPickupSelected(storePickupSelected);
    }, [storePickupSelected]);

    // DOM listener for immediate Ship/Pickup changes.
    useEffect(() => {
        const handleShippingMethodChange = () => {
            const container = document.getElementById('shipping-method');
            if ( ! container ) return;

            const allOptions = container.querySelectorAll('.wc-block-checkout__shipping-method-option');
            const selectedOption = container.querySelector('.wc-block-checkout__shipping-method-option--selected');

            if (selectedOption && allOptions.length > 0) {
                // Find the index of the selected option
                const selectedIndex = Array.from(allOptions).indexOf(selectedOption);
                const isPickup = selectedIndex === 1; // Ship = 0, Pickup = 1

                console.log('Selected shipping method index:', selectedIndex);
                console.log('isPickup shipping method:', isPickup);

                if (isPickup) {
                    clearValidationError(validationErrorId);
                }
                setIsPickupSelected(isPickup);
            }
        };

        // Initial check.
        handleShippingMethodChange();

        // Listen for clicks on shipping method container.
        const container = document.getElementById('shipping-method');
        if (container) {
            container.addEventListener('click', (event) => {
                // Small delay to allow DOM to update.
                setTimeout( handleShippingMethodChange, 300 );
            });

            // Also listen for any changes in the container.
            const observer = new MutationObserver(() => {
                handleShippingMethodChange();
            });

            observer.observe(container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });

            return () => {
                observer.disconnect();
            };
        }
    }, []);

    function useSiuskTerminalsList() {
        if (wcSettings.siusk_24_block_data && wcSettings.siusk_24_block_data.terminals) {
            return wcSettings.siusk_24_block_data.terminals;
        }
        return [];
    }

    const configured_shipping_methods = useSiuskTerminalsList();

    if (typeof shippingRates != 'undefined' && shippingRates !== null) {
        let firstObjKey = shippingRates[Object.keys( shippingRates )[0]];
        if (typeof firstObjKey != 'undefined' && firstObjKey !== null) {
            if ( firstObjKey.hasOwnProperty( 'shipping_rates' ) ) {
                const shippingRatesArray           = firstObjKey.shipping_rates;
                const shippingRatesExcludingPickup = [];
                if (typeof shippingRatesArray != 'undefined' && shippingRatesArray !== null) {
                    for (let method of shippingRatesArray) {
                        if (method.method_id === 'pickup_location') {
                            continue;
                        }
                        if (method.selected === true) {

                            selectedShippingInstanceID   = method.instance_id;
                            let selectedShippingMethodID = method.method_id;
                            if (selectedShippingMethodID.indexOf( 'siusk24_terminal_' ) !== -1) {
                                isSiuskShippingMethod = true;
                            } else {
                                /*let ship_method_data = configured_shipping_methods[selectedShippingInstanceID];
                                if (typeof ship_method_data != 'undefined' && ship_method_data !== null) {
                                    if (ship_method_data.hasOwnProperty( "need_map" )) {
                                        isSiuskShippingMethod = true;
                                    }
                                }*/
                            }
                        }
                        shippingRatesExcludingPickup.push( method );
                    }


                    if ( ! selectedShippingInstanceID && shippingRatesExcludingPickup.length > 0 ) {
                        const shipping_section = document.getElementsByClassName( 'wc-block-components-shipping-rates-control' )[0];
                        if (typeof shipping_section != 'undefined' && shipping_section !== null) {
                            const checkedRadioControl = shipping_section.querySelector( 'input[name^="radio-control-"]:checked' );
                            if (typeof checkedRadioControl != 'undefined' && checkedRadioControl !== null) {
                                let shipping_method_id = checkedRadioControl.getAttribute( 'id' );
                                if (typeof shipping_method_id != 'undefined' && shipping_method_id !== null) {
                                    let shipping_method_data = shipping_method_id.split( ":" );

                                    selectedShippingInstanceID = shipping_method_data[shipping_method_data.length - 1];

                                    let selectedShippingMethodRadioID = shipping_method_data[0];
                                    if (selectedShippingMethodRadioID.indexOf( 'siusk24_terminal_' ) !== -1) {
                                        isSiuskShippingMethod = true;
                                    } else {
                                        /*let ship_method_data = configured_shipping_methods[selectedShippingInstanceID];
                                        if (typeof ship_method_data != 'undefined' && ship_method_data !== null) {
                                            if (ship_method_data.hasOwnProperty( "need_map" )) {
                                                isSiuskShippingMethod = true;
                                            }
                                        }*/
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    // Initial validation error - prevent to place order after Checkout page loaded, and we have empty value in inpost hidden input (no point code yet).
    const initialValiadtion = useCallback(
        function () {
            if ( isSiuskShippingMethod && ! siuskTerminalID ) {
                setValidationErrors(
                    {
                        [validationErrorId]: {
                            message: __( 'Terminal ID must be chosen.', 'siusk24' ),
                            hidden: true,
                        },
                    }
                );
            }
        },
        [siuskTerminalID, setValidationErrors, clearValidationError, isSiuskShippingMethod]
    );


    // Additional validation: clear validation error if we have value for inpost hidden input with point code.
    const validateInput = useCallback(
        function () {
            if (siuskTerminalID || ! isSiuskShippingMethod) {
                clearValidationError( validationErrorId );
                return true; // Allow order placement.
            }
        },
        [siuskTerminalID, setValidationErrors, clearValidationError, isSiuskShippingMethod]
    );


    // Effect to validate input whenever Delivery Point input value changes.
    useEffect(
        function () {
            initialValiadtion();
            validateInput();
            setExtensionData( "siusk24", "terminal-id", siuskTerminalID );
            setExtensionData( "siusk24", "terminal-method-id", siuskTerminalMethodID );
            setExtensionData( "siusk24", "service-id", siusk24ShippingService );
        },
        [siuskTerminalID, siuskTerminalMethodID, siusk24ShippingService, setExtensionData, validateInput]
    );


    const [ shippingMethods, setShippingMethods ] = useState( [] );

    useEffect(
        function () {
            // Function to get shipping methods from the DOM.
            const getShippingMethods = function () {
                const shipping_block_html = document.querySelector(
                    '.wc-block-components-shipping-rates-control'
                );
                if (shipping_block_html) {
                    const shipping_radio_buttons = shipping_block_html.querySelectorAll(
                        'input[name^="radio-control-"]'
                    );
                    return Array.from( shipping_radio_buttons ).map(
                        function (radio) {
                            return {
                                id: radio.id,
                                value: radio.value,
                                checked: radio.checked
                            };
                        }
                    );
                }
                return [];
            };

            // Update shipping methods.
            const updateShippingMethods = function () {
                setShippingMethods( getShippingMethods() );
            };

            // Initial update.
            updateShippingMethods();

            // Set up a MutationObserver to watch for DOM changes.
            const observer = new MutationObserver( updateShippingMethods );
            observer.observe( document.body, { subtree: true, childList: true } );

            // Cleanup function.
            return () => observer.disconnect();
        },
        []
    );


    // Change Delivery Point input value.
    const handleSiuskTerminalChange = function (event) {
        const selectedId = event.target.value;
        setsiuskTerminalID( selectedId );
    };

    const handleSiuskTerminalMethodIDChange = function (event) {
        const selectedId = event.target.value;
        setsiuskTerminalMethodID( selectedId );
    };

    // Change Shipping Service input value.
    const handlesiusk24ShippingServiceChange = (event) => {
        const selectedId            = event.target.value;
        setSiusk24ShippingService( selectedId );
    };



    return (
        <>
            { ! isPickupSelected && (
                <>
                    <ExperimentalOrderMeta>
                        <TerminalIDInput
                            siuskTerminalID       = {siuskTerminalID}
                            handleSiuskTerminalChange = {handleSiuskTerminalChange}
                            siuskTerminalMethodID       = {siuskTerminalMethodID}
                            handleSiuskTerminalMethodIDChange = {handleSiuskTerminalMethodIDChange}
                            siusk24ShippingService={siusk24ShippingService}
                            handlesiusk24ShippingServiceChange={handlesiusk24ShippingServiceChange}
                        />
                    </ExperimentalOrderMeta>
                </>
            )}
        </>
    );


};


function TerminalIDInput({ handleSiuskTerminalChange, siuskTerminalID, handleSiuskTerminalMethodIDChange, siuskTerminalMethodID, handlesiusk24ShippingServiceChange, siusk24ShippingService }) {

    return (
        <div className="siuks24-terminal-id-wrap" style={{display: 'none'}}>
            <input
                value={siuskTerminalID}
                type="text"
                id="siusk24_terminal"
                onChange={handleSiuskTerminalChange}
            />
            <input
                value={siuskTerminalMethodID}
                type="text"
                id="siusk24_terminal_shipping_method"
                onChange={handleSiuskTerminalMethodIDChange}
            />
            <input
                value    = {siusk24ShippingService}
                type     = "text"
                id       = "siusk_24_service"
                onChange = {handlesiusk24ShippingServiceChange}
            />
        </div>
    );
}

