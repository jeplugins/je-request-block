/**
 * Request Board - Save Component
 */

import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
    const {
        title,
        showTitle,
        showForm,
        showFilter,
        showSort,
        defaultSort,
        defaultStatus,
        layout,
        theme,
        primaryColor,
    } = attributes;

    const blockProps = useBlockProps.save( {
        className: `je-request-board layout-${layout} theme-${theme}`,
        'data-show-form': showForm,
        'data-show-filter': showFilter,
        'data-show-sort': showSort,
        'data-default-sort': defaultSort,
        'data-default-status': defaultStatus,
        'data-primary-color': primaryColor,
    } );

    return (
        <div { ...blockProps }>
            { showTitle && (
                <h2 className="je-request-board__title">{ title }</h2>
            ) }

            <div className="je-request-board__app" data-loading="true">
                {/* React app will mount here */}
                <div className="je-request-board__loading">
                    Loading...
                </div>
            </div>
        </div>
    );
}
