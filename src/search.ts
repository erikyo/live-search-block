import { __, _n, sprintf } from '@wordpress/i18n';

import { SEARCH_DEBOUNCE_TIME, SEARCH_MIN_LENGTH } from './constants';
import { debounce, hideSpinner, liveSearch, showSpinner } from './utils';

/**
 * The `initLiveSearch` function initializes a live search functionality for a search input element in
 * a React TypeScript application.
 *
 * @param searchBlock - The `searchBlock` parameter is the HTML element that contains the search input
 *                    field and the search results. It is the parent element that holds all the elements related to the
 *                    search functionality.
 */
export function initLiveSearch(searchBlock: Element) {
	let searchResultsWrapper: HTMLElement | null = null;
	let isWrapperFocused = false;
	let searchInput = searchBlock?.querySelector(
		'input.wp-block-search__input'
	) as HTMLInputElement | null;

  // get the post type to search
  let postType = 'post,product';
  if (searchBlock.classList.contains('live-search-products')) {
    postType = 'product';
  } else if (searchBlock.classList.contains('live-search-post')) {
    postType = 'post';
  }

  // get the result count from the live search block
  let searchBlockClasses = searchBlock?.classList;
  // search into the classList of the live search block if there is a class that starts with 'live-search-count-' and use that number as the result count
  let resultCount = Number(liveSearchBlock?.resultCount || 10);
  if (searchBlockClasses) {
    for (let i = 0; i < searchBlockClasses.length; i++) {
      if (searchBlockClasses[i].startsWith('live-search-count-')) {
        resultCount = Number(searchBlockClasses[i].replace('live-search-count-', ''));
      }
    }
  }

	const searchButton = searchBlock?.querySelector('.wp-block-search__button');
	const liveSearchType = searchInput ? 'input' : 'modal';

	if (!searchBlock || !searchButton) {
		return;
	}

	function appendModal() {
		// Create modal overlay
		const modalOverlay = document.createElement('div');
		modalOverlay.classList.add('search-modal-overlay');
		modalOverlay.classList.add('hide');

		// Create modal container
		const modal = document.createElement('div');
		modal.classList.add('search-modal');

		// Create close button
		const closeButton = document.createElement('button');
		closeButton.classList.add('search-modal-close');
		closeButton.innerHTML = '&times;';
		closeButton.type = 'button'; // Ensure it doesn't submit the form

		// Create title
		const title = document.createElement('h2');
		title.classList.add('search-modal-title');
		title.textContent = __('Search', 'live-search-block');

		// Create the search form
		const searchForm = document.createElement('form');
		searchForm.classList.add('search-modal-form');
		searchForm.setAttribute('role', 'search');
		searchForm.setAttribute('method', 'get');
		searchForm.setAttribute('action', liveSearchBlock.formRedirectUrl);

		// Create modal container
		const inputWrap = document.createElement('div');
		inputWrap.classList.add('search-input-wrapper');

		// Create input field
		const input = document.createElement('input');
		input.classList.add('search-modal-input');
		input.setAttribute('type', 'search');
		input.setAttribute('name', 's'); // WordPress default search parameter
		input.setAttribute(
			'placeholder',
			__('Type to search…', 'live-search-block')
		);

		// Create search button
		const htmlButtonElement = document.createElement('button');
		htmlButtonElement.classList.add('search-modal-button');
		htmlButtonElement.setAttribute('type', 'submit');
		htmlButtonElement.innerHTML =
			'<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24"><path d="M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-2.9c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"></path></svg>';

		// Create search result container
		const searchResultWrapper = document.createElement('div');
		searchResultWrapper.classList.add('search-results');
		searchResultWrapper.classList.add('search-modal-results');

		// Append elements to form
		inputWrap.appendChild(input);
		inputWrap.appendChild(htmlButtonElement);
		searchForm.appendChild(inputWrap);

		// Append elements to modal
		modal.appendChild(closeButton);
		modal.appendChild(title);
		modal.appendChild(searchForm);
		modal.appendChild(searchResultWrapper);

		// Append modal to overlay
		modalOverlay.appendChild(modal);
		document.body.appendChild(modalOverlay);

		// Close modal on button click
		closeButton.addEventListener('click', (e) => {
			if (e.target === closeButton) {
				modalOverlay.classList.add('hide');
			}
		});

		// Close modal on outside click
		modalOverlay.addEventListener('click', (e) => {
			if (e.target === modalOverlay) {
				modalOverlay.classList.add('hide');
			}
		});

		// Handle form submission with live search
		searchForm.addEventListener('submit', (e) => {
			const searchTerm = input.value;

			// If we have exactly one result in our live search, redirect to it
			const searchResults = searchResultWrapper?.querySelectorAll(
				'.search-result-post'
			);
			if (searchResults && searchResults.length === 1) {
				e.preventDefault();
				const firstResult = searchResults[0].querySelector(
					'a'
				) as HTMLAnchorElement | null;
				if (firstResult) {
					const url = firstResult.href;
					if (url) {
						window.location.href = url;
						return;
					}
				}
			}

			// If there's not enough characters, prevent submission and show message
			if (
				searchTerm.length > 0 &&
				searchTerm.trim().length < SEARCH_MIN_LENGTH
			) {
				e.preventDefault();
				searchResultWrapper.innerHTML = `<ul class="search-results-wrapper"><li>${sprintf(
					/** Translators: 1: Number of characters, 2: Number of characters */
					_n(
						'Please add %s more characters',
						'Please add %s more character',
						SEARCH_MIN_LENGTH - searchTerm.trim().length,
						'live-search-block'
					),
					SEARCH_MIN_LENGTH - searchTerm.trim().length
				)}</li></ul>`;
				showSpinner(searchResultWrapper);
			}

			// Otherwise, let the form submit normally to WordPress search
		});

		// Still perform live search on button click
		htmlButtonElement.addEventListener('click', () => {
			modalOverlay.style.opacity = String(1);
			const searchTerm = input.value.trim();
			if (searchTerm.length >= SEARCH_MIN_LENGTH) {
				debouncedSearch(searchTerm);
			}
		});

		return modalOverlay;
	}

	/**
	 * The function appends a search results wrapper to a search input element and returns the wrapper.
	 * @return the searchResultsWrapper, which is a newly created div element with the class
	 * 'search-results'.
	 */
	function appendWrapper() {
		//the search results wrapper
		const resultsWrapper = document.createElement('div');
		resultsWrapper.classList.add('search-results');
		resultsWrapper.classList.add('search-input-results');

		// append the wrapper inside the search input
		searchBlock?.appendChild(resultsWrapper);

		// then add into it's wrapper the focus event listener
		searchBlock?.addEventListener('focusin', function () {
			isWrapperFocused = true;
		});

		searchBlock?.addEventListener('focusout', function () {
			isWrapperFocused = false;
		});

		return resultsWrapper;
	}

	function removeWrapper(wrapper: Element | null) {
		if (!wrapper) {
		} else if (liveSearchType === 'modal') {
			wrapper.innerHTML = '';
		} else {
			wrapper.remove();
			wrapper = null;
		}
	}

	/* The `debouncedSearch` function is a debounced version of the `liveSearch` function. It is created
	using the `debounce` utility function, which ensures that the `liveSearch` function is only called
	after a certain delay (specified by `SEARCH_DEBOUNCE_TIME`) since the last invocation of the
	`debouncedSearch` function. */
	const debouncedSearch = debounce((searchTerm: string) => {
		if (searchResultsWrapper === null) {
			return;
		}
		searchBlock.classList.remove('hide');

		if (searchTerm.length >= SEARCH_MIN_LENGTH) {
			liveSearch(
				searchTerm,
        postType,
        resultCount
			).then((resultsHtml) => {
				hideSpinner(searchResultsWrapper);

				// adds the focused class to the search block to show the search results
				searchBlock?.classList.add('focused');

				if (!searchResultsWrapper) {
					return;
				}

				searchResultsWrapper.classList.remove('awaitingResponse');

				// append the results to the search results wrapper
				if (resultsHtml) {
					searchResultsWrapper.innerHTML = `<ul class="search-results-wrapper">${resultsHtml}</ul>`;
				} else {
					searchResultsWrapper.innerHTML = `<ul class="search-results-wrapper"><li class="no-results">${__(
						'Sorry, no results found',
						'live-search-block'
					)}</li></ul>`;
				}
			});
		} else if (searchTerm.length !== 0) {
			// the minimum number of characters is 3
			searchResultsWrapper.innerHTML = `<ul class="search-results-wrapper"><li>${sprintf(
				/** Translators: 1: Number of characters, 2: Number of characters */
				_n(
					'Please add %s more characters',
					'Please add %s more character',
					SEARCH_MIN_LENGTH - searchTerm.length,
					'live-search-block'
				),
				SEARCH_MIN_LENGTH - searchTerm.length
			)}</li></ul>`;
			showSpinner(searchResultsWrapper);
		} else {
			// remove the search results wrapper
			removeWrapper(searchResultsWrapper);
		}
	}, SEARCH_DEBOUNCE_TIME);

	/**
	 * The `activate` function adds event listeners to the search input element for keyup, focus, and blur
	 * events.
	 */
	function activateLiveSearch() {
		// If the search input is not in a form, wrap it in one
		if (
			searchInput &&
			liveSearchType === 'input' &&
			!searchInput.closest('form')
		) {
			const parentElement = searchInput.parentElement;
			const searchBtn = searchInput.nextElementSibling;

			// Create a form element
			const form = document.createElement('form');
			form.setAttribute('role', 'search');
			form.setAttribute('method', 'get');
			form.setAttribute('action', window.location.origin);
			form.classList.add('search-form');

			// Set name attribute on the search input for WordPress
			searchInput.setAttribute('name', 's');

			// Replace the input with the form
			if (parentElement) {
				// Remove the input from its current location
				searchInput.remove();
				if (searchBtn) {
					searchBtn.remove();
				}

				// Add the input to the form
				form.appendChild(searchInput);
				if (searchBtn) {
					form.appendChild(searchBtn);
				}

				// Add the form to the parent
				parentElement.appendChild(form);

				// Add form submission event listener
				form.addEventListener('submit', (e) => {
					const searchTerm = searchInput?.value.trim() || '';

					// If we have exactly one result in our live search, redirect to it
					const searchResults =
						searchResultsWrapper?.querySelectorAll(
							'.search-result-post'
						);
					if (searchResults && searchResults.length === 1) {
						e.preventDefault();
						const firstResult = searchResults[0].querySelector(
							'a'
						) as HTMLAnchorElement | null;
						if (firstResult) {
							const url = firstResult.href;
							if (url) {
								window.location.href = url;
								return;
							}
						}
					}

					// If there's not enough characters, prevent submission and show message
					if (
						searchTerm.length > 0 &&
						searchTerm.length < SEARCH_MIN_LENGTH
					) {
						e.preventDefault();
						if (
							!searchResultsWrapper &&
							liveSearchType === 'input'
						) {
							searchResultsWrapper = appendWrapper();
						}

						if (searchResultsWrapper) {
							searchResultsWrapper.innerHTML = `<ul class="search-results-wrapper"><li>${sprintf(
								/** Translators: 1: Number of characters, 2: Number of characters */
								_n(
									'Please add %s more characters',
									'Please add %s more character',
									SEARCH_MIN_LENGTH - searchTerm.length
								),
								SEARCH_MIN_LENGTH - searchTerm.length
							)}</li></ul>`;
							showSpinner(searchResultsWrapper);
						}
					}

					// Otherwise, let the form submit normally to WordPress search
				});
			}
		}

		searchInput?.addEventListener('input', (e) => {
			if (
				!(e.currentTarget as HTMLInputElement)?.value &&
				liveSearchType === 'input'
			) {
				removeWrapper(searchResultsWrapper);
			}
		});

		/* When the user releases a key after typing in the search input, the event listener function is
		triggered. */
		searchInput?.addEventListener('keyup', (e) => {
			const input = e.target as HTMLInputElement;
			const searchTerm = input.value;

			// append the search results wrapper if it doesn't exist
			if (!searchResultsWrapper && liveSearchType === 'input') {
				searchResultsWrapper = appendWrapper();
			}

			// show the loading spinner if the spinner isn't show
			if (
				searchResultsWrapper &&
				!searchResultsWrapper?.classList.contains('awaitingResponse')
			) {
				showSpinner(searchResultsWrapper);
			}

			debouncedSearch(searchTerm);
		});

		/* adds an event listener to the `searchInput` element for the `focus` event. */
		searchInput?.addEventListener('focus', function () {
			searchBlock?.classList.add('focused');
		});

		/* The code snippet adds an event listener to the `searchInput` element for the `blur` event. */
		searchInput?.addEventListener('blur', function (e) {
			if (
				!isWrapperFocused &&
				!(e.target as HTMLInputElement).classList.contains(
					'wp-block-search__input'
				)
			) {
				searchBlock?.classList.remove('focused');
			}
		});
	}

	// if there is no search input, it's a search button, and we need to create the modal
	if (liveSearchType === 'modal') {
		const modal = appendModal();
		searchInput = modal.querySelector('.search-modal-input');
		searchInput?.focus();
		searchResultsWrapper = modal.querySelector('.search-results');

		// When the search button is clicked, show the modal
		searchButton.addEventListener('click', function (event) {
			event.preventDefault();
			activateLiveSearch();
			modal.classList.remove('hide');
			searchInput?.focus();
		});
	} else {
		// Initialize the script
		activateLiveSearch();
	}
}
