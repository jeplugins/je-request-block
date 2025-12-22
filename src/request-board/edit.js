/**
 * Request Board - Editor Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    ToggleControl,
    SelectControl,
} from '@wordpress/components';
import './editor.scss';

// Get Pro status from WordPress
const isPro = window.jeRequestData?.isPro || false;

export default function Edit( { attributes, setAttributes } ) {
    const {
        title,
        showTitle,
        showForm,
        showFilter,
        showSort,
        defaultSort,
        defaultStatus,
        layout,
    } = attributes;

    const blockProps = useBlockProps( {
        className: `je-request-board layout-${layout}`,
    } );

    // Sample data for preview
    const sampleRequests = [
        { id: 1, title: 'Dark Mode Support', votes: 47, status: 'planned' },
        { id: 2, title: 'Export to PDF', votes: 32, status: 'pending' },
        { id: 3, title: 'Mobile App', votes: 28, status: 'in_progress' },
    ];

    const getStatusLabel = ( status ) => {
        const labels = {
            pending: __( 'Pending', 'je-request-block' ),
            planned: __( 'Planned', 'je-request-block' ),
            in_progress: __( 'In Progress', 'je-request-block' ),
            completed: __( 'Completed', 'je-request-block' ),
            rejected: __( 'Rejected', 'je-request-block' ),
        };
        return labels[ status ] || status;
    };

    const getStatusClass = ( status ) => {
        return `status-${status}`;
    };

    return (
        <>
            <InspectorControls>
                {/* Pro Upsell Panel */}
                {!isPro && (
                    <PanelBody 
                        title={ __( 'Get Pro Features', 'je-request-block' ) } 
                        initialOpen={ true }
                    >
                        <div style={{ color: '#1e3a5f' }}>
                            <p style={{ marginBottom: '12px' }}>
                                { __( 'Unlock all features:', 'je-request-block' ) }
                            </p>
                            <ul style={{ 
                                color: '#374151', 
                                listStyle: 'disc', 
                                paddingLeft: '20px', 
                                marginBottom: '16px',
                                marginTop: '0'
                            }}>
                                <li>6 Color Themes</li>
                                <li>Custom Primary Color</li>
                                <li>Custom Statuses</li>
                                <li>Categories/Tags</li>
                                <li>Admin Moderation</li>
                                <li>Email Notifications</li>
                            </ul>
                            <a
                                href="https://jeplugins.github.io/request-block/"
                                target="_blank"
                                rel="noopener noreferrer"
                                style={{
                                    display: 'inline-block',
                                    backgroundColor: '#2563eb',
                                    color: '#ffffff',
                                    padding: '8px 16px',
                                    borderRadius: '4px',
                                    textDecoration: 'none',
                                    fontSize: '13px'
                                }}
                            >
                                { __( 'Get Pro Version', 'je-request-block' ) } →
                            </a>
                        </div>
                    </PanelBody>
                )}

                <PanelBody title={ __( 'Settings', 'je-request-block' ) }>
                    <TextControl
                        label={ __( 'Title', 'je-request-block' ) }
                        value={ title }
                        onChange={ ( value ) => setAttributes( { title: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Title', 'je-request-block' ) }
                        checked={ showTitle }
                        onChange={ ( value ) => setAttributes( { showTitle: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Submit Form', 'je-request-block' ) }
                        checked={ showForm }
                        onChange={ ( value ) => setAttributes( { showForm: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Filter', 'je-request-block' ) }
                        checked={ showFilter }
                        onChange={ ( value ) => setAttributes( { showFilter: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Sort', 'je-request-block' ) }
                        checked={ showSort }
                        onChange={ ( value ) => setAttributes( { showSort: value } ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Display Options', 'je-request-block' ) } initialOpen={ false }>
                    <SelectControl
                        label={ __( 'Layout', 'je-request-block' ) }
                        value={ layout }
                        options={ [
                            { label: __( 'List', 'je-request-block' ), value: 'list' },
                            { label: __( 'Cards', 'je-request-block' ), value: 'cards' },
                        ] }
                        onChange={ ( value ) => setAttributes( { layout: value } ) }
                    />
                    <SelectControl
                        label={ __( 'Default Sort', 'je-request-block' ) }
                        value={ defaultSort }
                        options={ [
                            { label: __( 'Most Votes', 'je-request-block' ), value: 'votes' },
                            { label: __( 'Newest', 'je-request-block' ), value: 'date' },
                        ] }
                        onChange={ ( value ) => setAttributes( { defaultSort: value } ) }
                    />
                    <SelectControl
                        label={ __( 'Default Status Filter', 'je-request-block' ) }
                        value={ defaultStatus }
                        options={ [
                            { label: __( 'All', 'je-request-block' ), value: 'all' },
                            { label: __( 'Pending', 'je-request-block' ), value: 'pending' },
                            { label: __( 'Planned', 'je-request-block' ), value: 'planned' },
                            { label: __( 'In Progress', 'je-request-block' ), value: 'in_progress' },
                            { label: __( 'Completed', 'je-request-block' ), value: 'completed' },
                        ] }
                        onChange={ ( value ) => setAttributes( { defaultStatus: value } ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                { showTitle && (
                    <h2 className="je-request-board__title">{ title }</h2>
                ) }

                { showForm && (
                    <div className="je-request-board__form">
                        <div className="je-request-form">
                            <input
                                type="text"
                                placeholder={ __( 'Enter your feature request...', 'je-request-block' ) }
                                className="je-request-form__input"
                                disabled
                            />
                            <textarea
                                placeholder={ __( 'Describe your request (optional)', 'je-request-block' ) }
                                className="je-request-form__textarea"
                                disabled
                            />
                            <div className="je-request-form__row">
                                <input
                                    type="email"
                                    placeholder={ __( 'Your email (optional)', 'je-request-block' ) }
                                    className="je-request-form__input"
                                    disabled
                                />
                                <button className="je-request-form__button" disabled>
                                    { __( 'Submit Request', 'je-request-block' ) }
                                </button>
                            </div>
                        </div>
                    </div>
                ) }

                { ( showFilter || showSort ) && (
                    <div className="je-request-board__controls">
                        { showFilter && (
                            <select className="je-request-control" disabled>
                                <option>{ __( 'All Status', 'je-request-block' ) }</option>
                            </select>
                        ) }
                        { showSort && (
                            <select className="je-request-control" disabled>
                                <option>{ __( 'Most Votes', 'je-request-block' ) }</option>
                            </select>
                        ) }
                    </div>
                ) }

                <div className="je-request-board__list">
                    { sampleRequests.map( ( request ) => (
                        <div key={ request.id } className="je-request-item">
                            <div className="je-request-item__votes">
                                <button className="je-request-item__vote-btn" disabled>▲</button>
                                <span className="je-request-item__vote-count">{ request.votes }</span>
                            </div>
                            <div className="je-request-item__content">
                                <h3 className="je-request-item__title">{ request.title }</h3>
                                <span className={ `je-request-item__status ${getStatusClass( request.status )}` }>
                                    { getStatusLabel( request.status ) }
                                </span>
                            </div>
                        </div>
                    ) ) }
                </div>

                <p className="je-request-board__preview-note">
                    { __( 'Preview only - Interactive on frontend', 'je-request-block' ) }
                </p>
            </div>
        </>
    );
}