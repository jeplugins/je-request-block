/**
 * Frontend Interactive App
 * 
 * Hybrid Voting System (IP + Cookie UUID)
 * Uses localStorage with cookie fallback for UUID persistence
 */

( function() {
    'use strict';

    /**
     * Get or create voter UUID
     * Uses localStorage with cookie fallback
     * 
     * @return {string} UUID string
     */
    function getOrCreateVoterUUID() {
        const STORAGE_KEY = 'je_voter_uuid';
        const COOKIE_NAME = 'je_voter_uuid';
        const COOKIE_DAYS = 365;

        // Try localStorage first
        let uuid = null;
        try {
            uuid = localStorage.getItem( STORAGE_KEY );
        } catch ( e ) {
            // localStorage not available
        }

        // Fallback to cookie
        if ( ! uuid ) {
            const cookies = document.cookie.split( ';' );
            for ( let cookie of cookies ) {
                const [ name, value ] = cookie.trim().split( '=' );
                if ( name === COOKIE_NAME && value ) {
                    uuid = decodeURIComponent( value );
                    break;
                }
            }
        }

        // Generate new UUID if not found
        if ( ! uuid ) {
            uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function( c ) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : ( r & 0x3 | 0x8 );
                return v.toString( 16 );
            } );

            // Save to localStorage
            try {
                localStorage.setItem( STORAGE_KEY, uuid );
            } catch ( e ) {
                // localStorage not available
            }

            // Save to cookie as fallback
            const expires = new Date();
            expires.setTime( expires.getTime() + ( COOKIE_DAYS * 24 * 60 * 60 * 1000 ) );
            document.cookie = `${COOKIE_NAME}=${encodeURIComponent( uuid )};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
        }

        return uuid;
    }

    // Get UUID once at initialization
    const voterUUID = getOrCreateVoterUUID();

    const boards = document.querySelectorAll( '.je-request-board' );

    boards.forEach( ( board ) => {
        const app = board.querySelector( '.je-request-board__app' );
        if ( ! app ) return;

        const config = {
            showForm: board.dataset.showForm === 'true',
            showFilter: board.dataset.showFilter === 'true',
            showSort: board.dataset.showSort === 'true',
            defaultSort: board.dataset.defaultSort || 'votes',
            defaultStatus: board.dataset.defaultStatus || 'all',
            primaryColor: board.dataset.primaryColor || '#4f46e5',
        };

        let state = {
            requests: [],
            loading: true,
            sort: config.defaultSort,
            status: config.defaultStatus,
            submitting: false,
            message: null,
        };

        // Fetch requests
        async function fetchRequests() {
            try {
                const params = new URLSearchParams();
                if ( state.status !== 'all' ) {
                    params.append( 'status', state.status );
                }
                params.append( 'sort', state.sort );

                const response = await fetch(
                    `${jeRequestData.restUrl}requests?${params.toString()}`,
                    {
                        headers: {
                            'X-WP-Nonce': jeRequestData.nonce,
                            'X-Voter-UUID': voterUUID,
                        },
                    }
                );

                if ( ! response.ok ) throw new Error( 'Failed to fetch' );

                state.requests = await response.json();
                state.loading = false;
                render();
            } catch ( error ) {
                console.error( 'Error fetching requests:', error );
                state.loading = false;
                render();
            }
        }

        // Submit request
        async function submitRequest( title, description, email ) {
            state.submitting = true;
            state.message = null;
            render();

            try {
                const response = await fetch( `${jeRequestData.restUrl}requests`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': jeRequestData.nonce,
                    },
                    body: JSON.stringify( { 
                        title, 
                        description, 
                        email,
                        voter_uuid: voterUUID,
                    } ),
                } );

                const data = await response.json();

                if ( ! response.ok ) {
                    throw new Error( data.message || 'Failed to submit' );
                }

                state.requests.unshift( data.request );
                state.message = { type: 'success', text: 'Request submitted successfully!' };
                state.submitting = false;
                render();

                // Clear form
                const form = board.querySelector( '.je-request-form' );
                if ( form ) {
                    form.querySelector( 'input[name="title"]' ).value = '';
                    const desc = form.querySelector( 'textarea[name="description"]' );
                    if ( desc ) desc.value = '';
                    const emailInput = form.querySelector( 'input[name="email"]' );
                    if ( emailInput ) emailInput.value = '';
                }

            } catch ( error ) {
                state.message = { type: 'error', text: error.message };
                state.submitting = false;
                render();
            }
        }

        // Vote/Unvote for request
        async function vote( requestId ) {
            try {
                const response = await fetch(
                    `${jeRequestData.restUrl}requests/${requestId}/vote`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': jeRequestData.nonce,
                        },
                        body: JSON.stringify( {
                            voter_uuid: voterUUID,
                        } ),
                    }
                );

                const data = await response.json();

                if ( ! response.ok ) {
                    throw new Error( data.message || 'Failed to vote' );
                }

                // Update local state
                const request = state.requests.find( r => r.id === requestId );
                if ( request ) {
                    request.votes = data.votes;
                    request.hasVoted = data.hasVoted;
                }
                render();

            } catch ( error ) {
                alert( error.message );
            }
        }

        // Get status label
        function getStatusLabel( status ) {
            const labels = {
                pending: 'Pending',
                planned: 'Planned',
                in_progress: 'In Progress',
                completed: 'Completed',
                rejected: 'Rejected',
            };
            return labels[ status ] || status;
        }

        // Format date
        function formatDate( dateString ) {
            const date = new Date( dateString );
            return date.toLocaleDateString( 'en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            } );
        }

        // Render
        function render() {
            let html = '';

            // Form
            if ( config.showForm ) {
                html += `
                    <div class="je-request-board__form">
                        <form class="je-request-form" onsubmit="return false;">
                            <input
                                type="text"
                                name="title"
                                class="je-request-form__input"
                                placeholder="Enter your feature request..."
                                required
                                minlength="5"
                            />
                            <textarea
                                name="description"
                                class="je-request-form__textarea"
                                placeholder="Describe your request (optional)"
                            ></textarea>
                            <div class="je-request-form__row">
                                <input
                                    type="email"
                                    name="email"
                                    class="je-request-form__input"
                                    placeholder="Your email (optional)"
                                />
                                <button
                                    type="submit"
                                    class="je-request-form__button"
                                    ${state.submitting ? 'disabled' : ''}
                                >
                                    ${state.submitting ? 'Submitting...' : 'Submit Request'}
                                </button>
                            </div>
                            ${state.message ? `
                                <div class="je-request-form__message ${state.message.type}">
                                    ${state.message.text}
                                </div>
                            ` : ''}
                        </form>
                    </div>
                `;
            }

            // Controls
            if ( config.showFilter || config.showSort ) {
                html += '<div class="je-request-board__controls">';

                if ( config.showFilter ) {
                    html += `
                        <select class="je-request-control" data-control="status">
                            <option value="all" ${state.status === 'all' ? 'selected' : ''}>All Status</option>
                            <option value="pending" ${state.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="planned" ${state.status === 'planned' ? 'selected' : ''}>Planned</option>
                            <option value="in_progress" ${state.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                            <option value="completed" ${state.status === 'completed' ? 'selected' : ''}>Completed</option>
                        </select>
                    `;
                }

                if ( config.showSort ) {
                    html += `
                        <select class="je-request-control" data-control="sort">
                            <option value="votes" ${state.sort === 'votes' ? 'selected' : ''}>Most Votes</option>
                            <option value="date" ${state.sort === 'date' ? 'selected' : ''}>Newest</option>
                        </select>
                    `;
                }

                html += '</div>';
            }

            // Loading
            if ( state.loading ) {
                html += '<div class="je-request-board__loading">Loading requests...</div>';
            }
            // Empty
            else if ( state.requests.length === 0 ) {
                html += '<div class="je-request-board__empty">No feature requests yet.</div>';
            }
            // List
            else {
                html += '<div class="je-request-board__list">';

                state.requests.forEach( ( request ) => {
                    html += `
                        <div class="je-request-item" data-id="${request.id}">
                            <div class="je-request-item__votes">
                                <button
                                    class="je-request-item__vote-btn ${request.hasVoted ? 'voted' : ''}"
                                    data-vote="${request.id}"
                                >
                                    ▲
                                </button>
                                <span class="je-request-item__vote-count">${request.votes}</span>
                            </div>
                            <div class="je-request-item__content">
                                <h3 class="je-request-item__title">${escapeHtml( request.title )}</h3>
                                ${request.description ? `
                                    <p class="je-request-item__description">${escapeHtml( request.description )}</p>
                                ` : ''}
                                <div class="je-request-item__meta">
                                    <span class="je-request-item__status status-${request.status}">
                                        ${getStatusLabel( request.status )}
                                    </span>
                                    <span class="je-request-item__date">${formatDate( request.date )}</span>
                                </div>
                            </div>
                        </div>
                    `;
                } );

                html += '</div>';
            }

            app.innerHTML = html;
            attachEvents();
        }

        // Escape HTML
        function escapeHtml( text ) {
            const div = document.createElement( 'div' );
            div.textContent = text;
            return div.innerHTML;
        }

        // Attach events
        function attachEvents() {
            // Form submit
            const form = app.querySelector( '.je-request-form' );
            if ( form ) {
                form.addEventListener( 'submit', ( e ) => {
                    e.preventDefault();
                    const title = form.querySelector( 'input[name="title"]' ).value;
                    const description = form.querySelector( 'textarea[name="description"]' )?.value || '';
                    const email = form.querySelector( 'input[name="email"]' )?.value || '';
                    submitRequest( title, description, email );
                } );
            }

            // Vote buttons
            app.querySelectorAll( '[data-vote]' ).forEach( ( btn ) => {
                btn.addEventListener( 'click', () => {
                    const requestId = parseInt( btn.dataset.vote, 10 );
                    vote( requestId );
                } );
            } );

            // Filter
            const statusSelect = app.querySelector( '[data-control="status"]' );
            if ( statusSelect ) {
                statusSelect.addEventListener( 'change', ( e ) => {
                    state.status = e.target.value;
                    fetchRequests();
                } );
            }

            // Sort
            const sortSelect = app.querySelector( '[data-control="sort"]' );
            if ( sortSelect ) {
                sortSelect.addEventListener( 'change', ( e ) => {
                    state.sort = e.target.value;
                    fetchRequests();
                } );
            }
        }

        // Initialize
        fetchRequests();
    } );
} )();