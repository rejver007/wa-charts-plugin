import { registerBlockType } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	ComboboxControl,
	ExternalLink,
	PanelBody,
	Placeholder,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import metadata from './block.json';

const titleOf = ( record ) =>
	( record &&
		record.title &&
		( record.title.raw || record.title.rendered ) ) ||
	/* translators: %d: chart ID. */
	sprintf( __( 'Chart #%d', 'wa-charts' ), record ? record.id : 0 );

function Edit( { attributes, setAttributes } ) {
	const { chartId, height, layout, legend } = attributes;
	const [ search, setSearch ] = useState( '' );

	const { charts, selected } = useSelect(
		( select ) => {
			const store = select( coreStore );
			return {
				charts:
					store.getEntityRecords( 'postType', 'wa_chart', {
						search,
						per_page: 20,
						status: [ 'publish', 'draft', 'private' ],
					} ) || [],
				selected: chartId
					? store.getEntityRecord( 'postType', 'wa_chart', chartId )
					: null,
			};
		},
		[ search, chartId ]
	);

	const options = charts.map( ( record ) => ( {
		value: record.id,
		label: titleOf( record ),
	} ) );
	if ( selected && ! options.some( ( o ) => o.value === selected.id ) ) {
		options.unshift( { value: selected.id, label: titleOf( selected ) } );
	}

	const picker = (
		<ComboboxControl
			label={ __( 'Chart', 'wa-charts' ) }
			value={ chartId || null }
			options={ options }
			onFilterValueChange={ setSearch }
			onChange={ ( value ) =>
				setAttributes( { chartId: value ? Number( value ) : 0 } )
			}
		/>
	);

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Chart settings', 'wa-charts' ) }>
					{ picker }
					<TextControl
						type="number"
						min={ 100 }
						max={ 2000 }
						label={ __( 'Height override (px)', 'wa-charts' ) }
						value={ height ?? '' }
						onChange={ ( value ) =>
							setAttributes( {
								height:
									value === '' ? undefined : Number( value ),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Layout override', 'wa-charts' ) }
						value={ layout }
						options={ [
							{
								value: '',
								label: __( 'Use chart setting', 'wa-charts' ),
							},
							{
								value: 'left',
								label: __(
									'Chart left, text right',
									'wa-charts'
								),
							},
							{
								value: 'right',
								label: __(
									'Chart right, text left',
									'wa-charts'
								),
							},
							{
								value: 'top',
								label: __( 'Chart above text', 'wa-charts' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<SelectControl
						label={ __( 'Legend override', 'wa-charts' ) }
						value={ legend }
						options={ [
							{
								value: '',
								label: __( 'Use chart setting', 'wa-charts' ),
							},
							{
								value: 'bottom',
								label: __( 'Below the chart', 'wa-charts' ),
							},
							{
								value: 'right',
								label: __( 'Right of the chart', 'wa-charts' ),
							},
							{
								value: 'none',
								label: __( 'Hidden', 'wa-charts' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { legend: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon="chart-pie"
				label={
					selected ? titleOf( selected ) : __( 'Chart', 'wa-charts' )
				}
				instructions={
					chartId
						? __(
								'The chart is shown on the published page.',
								'wa-charts'
							)
						: __(
								'Choose a chart created under Charts.',
								'wa-charts'
							)
				}
			>
				{ ! chartId && picker }
				{ chartId > 0 && (
					<ExternalLink
						href={ addQueryArgs( 'post.php', {
							post: chartId,
							action: 'edit',
						} ) }
					>
						{ __( 'Edit chart', 'wa-charts' ) }
					</ExternalLink>
				) }
			</Placeholder>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
