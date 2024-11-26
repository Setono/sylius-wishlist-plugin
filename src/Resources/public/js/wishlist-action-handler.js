/**
 * Handles the add to wishlist and remove from wishlist actions (i.e. toggling)
 */
class WishlistActionHandler {
    /**
     * @type {Object}
     */
    #options;

    /**
     * @param {Object} options
     * @param {Object} options.selector
     * @param {string} options.selector.toggle - Selector for the toggle wishlist button
     * @param {Object} options.callback
     * @param {Function} options.callback.onToggle - Callback function to call when the toggle wishlist button is clicked. The first argument is the event object, the second argument is the button element, and 'this' is bound to the wishlist manager
     */
    constructor(options = {}) {
        this.#options = Object.assign({
                selector: {
                    toggle: 'button.ssw-toggle',
                },
                callback: {
                    /**
                     * @param {Event} event
                     * @param {HTMLButtonElement} element
                     */
                    onToggle: async function (event, element) {
                        event.preventDefault();

                        const response = await fetch(element.dataset.url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        // todo how to handle this?
                        if (!response.ok) {
                            throw new Error(`Response status: ${response.status}`);
                        }

                        /**
                         * @type {Object}
                         * @property {string} event - Either 'added' or 'removed'
                         * @property {string} toggleUrl - The new URL to toggle the wishlist
                         * @property {string} wishlistItemsCount - The number of items in all wishlists
                         */
                        const json = await response.json();

                        element.dataset.url = json.toggleUrl;
                        element.classList.toggle('ssw-added', json.event === 'added');

                        element.dispatchEvent(new CustomEvent(`ssw:product-${json.event}`, {
                            bubbles: true,
                            detail: {
                                wishlistItemsCount: json.wishlistItemsCount,
                            },
                        }));
                    },
                }
            },
            options
        );

        document.addEventListener('click', (event) => {
            const element = event.target.closest(this.#options.selector.toggle);

            if(element) {
                this.#options.callback.onToggle.bind(this, event, element)();
            }
        });
    }
}

new WishlistActionHandler(window.sswWishlist || {});
