/**
 * TW Blocks — Gutenberg Editor Integration (Phase 2).
 *
 * Features:
 * 1. Universal "Tailwind Class" Inspector Control injected into ALL blocks (`editor.BlockEdit` filter):
 *    - Enforces `dir="ltr"` and `text-align: left` on Tailwind utility inputs even when the editor UI is RTL.
 *    - Live autocomplete for Tailwind CSS v4 utilities & logical RTL utilities (`ms-`, `me-`, `ps-`, `pe-`, `text-start`, `rtl:-scale-x-100`).
 *    - Warnings when physical directional utilities (`ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left`) are typed.
 *    - Per-breakpoint (`sm`, `md`, `lg`, `xl`) and per-state (`hover`, `focus`, `dark`, `rtl`) utility editors.
 *    - Visual logical spacing, typography, and color token pickers that write pure Tailwind utility classes (never inline `style=""`).
 *    - Synchronized with Gutenberg's responsive device preview switcher (Desktop / Tablet / Mobile).
 * 2. Debounced (400ms) Live Preview CSS synchronization:
 *    - Collects active utility classes across all blocks in the editor store, calls `POST /wp-json/tw-blocks/v1/compile-preview`,
 *      and injects the compiled CSS directly into the Gutenberg editor `iframe[name="editor-canvas"]` and parent document.
 * 3. Registers all 14 Phase 2 blocks (`apiVersion: 3`).
 */

( function ( wp ) {
	'use strict';

	const { __ } = wp.i18n;
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment, createElement: el, useState, useEffect, useMemo } = wp.element;
	const { InspectorControls, InnerBlocks, useBlockProps } = wp.blockEditor;
	const {
		PanelBody,
		TextControl,
		TextareaControl,
		SelectControl,
		ToggleControl,
		Button,
		ButtonGroup,
		Notice,
	} = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { registerBlockType } = wp.blocks;
	const ServerSideRender = wp.serverSideRender;

	const editorConfig = window.twbEditorConfig || {
		restCompileUrl: '/wp-json/tw-blocks/v1/compile-preview',
		restNonce: '',
		isRtl: true,
		locale: { lang: 'fa-IR', dir: 'rtl' },
		autocomplete: [],
	};

	const PHYSICAL_DIRECTION_REGEX = /(?:^|:)(?:ml-|mr-|pl-|pr-|left-|right-|text-left|text-right|border-l(?:$|-)|border-r(?:$|-)|rounded-l(?:$|-)|rounded-r(?:$|-))/;

	const BREAKPOINTS = [
		{ key: 'base', label: __( 'Base (All)', 'tw-blocks' ), device: 'Desktop' },
		{ key: 'sm', label: 'sm:', device: 'Mobile' },
		{ key: 'md', label: 'md:', device: 'Tablet' },
		{ key: 'lg', label: 'lg:', device: 'Desktop' },
		{ key: 'xl', label: 'xl:', device: 'Desktop' },
	];

	const STATES = [
		{ key: 'hover', label: 'hover:' },
		{ key: 'focus', label: 'focus:' },
		{ key: 'dark', label: 'dark:' },
		{ key: 'rtl', label: 'rtl:' },
	];

	const LOGICAL_SPACING_PRESETS = [
		{ label: 'ps-4 pe-4', value: 'ps-4 pe-4' },
		{ label: 'ps-6 pe-6', value: 'ps-6 pe-6' },
		{ label: 'py-8', value: 'py-8' },
		{ label: 'py-16', value: 'py-16' },
		{ label: 'ms-auto me-auto', value: 'ms-auto me-auto' },
		{ label: 'gap-4', value: 'gap-4' },
		{ label: 'gap-6', value: 'gap-6' },
	];

	const TYPOGRAPHY_PRESETS = [
		{ label: 'text-start', value: 'text-start' },
		{ label: 'text-center', value: 'text-center' },
		{ label: 'text-end', value: 'text-end' },
		{ label: 'leading-relaxed', value: 'leading-relaxed' },
		{ label: 'leading-loose', value: 'leading-loose' },
		{ label: 'font-bold', value: 'font-bold' },
		{ label: 'tracking-normal', value: 'tracking-normal' },
	];

	const COLOR_TOKEN_PRESETS = [
		{ label: 'bg-primary text-white', value: 'bg-primary text-white' },
		{ label: 'bg-surface text-foreground', value: 'bg-surface text-foreground' },
		{ label: 'bg-white text-slate-900', value: 'bg-white text-slate-900' },
		{ label: 'text-primary', value: 'text-primary' },
		{ label: 'border-s-4 border-primary', value: 'border-s-4 border-primary' },
		{ label: 'rtl:-scale-x-100', value: 'rtl:-scale-x-100' },
	];

	/**
	 * Inject compiled preview CSS into both the parent document and Gutenberg's `editor-canvas` iframe.
	 */
	function injectLivePreviewStyle( cssText ) {
		if ( ! cssText ) {
			return;
		}
		const styleId = 'tw-blocks-live-preview-css';
		let parentTag = document.getElementById( styleId );
		if ( ! parentTag ) {
			parentTag = document.createElement( 'style' );
			parentTag.id = styleId;
			document.head.appendChild( parentTag );
		}
		parentTag.textContent = cssText;

		const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
		if ( iframe && iframe.contentDocument && iframe.contentDocument.head ) {
			let iframeTag = iframe.contentDocument.getElementById( styleId );
			if ( ! iframeTag ) {
				iframeTag = iframe.contentDocument.createElement( 'style' );
				iframeTag.id = styleId;
				iframe.contentDocument.head.appendChild( iframeTag );
			}
			iframeTag.textContent = cssText;
		}
	}

	/**
	 * Debounced Live Preview Compiler manager.
	 */
	let debounceTimer = null;
	let lastCompiledSignature = '';

	function scheduleLivePreviewCompile( classTokens ) {
		const uniqueSorted = Array.from( new Set( classTokens.filter( Boolean ) ) ).sort();
		const signature = uniqueSorted.join( ' ' );
		if ( ! signature || signature === lastCompiledSignature ) {
			return;
		}

		if ( debounceTimer ) {
			clearTimeout( debounceTimer );
		}

		debounceTimer = setTimeout( function () {
			lastCompiledSignature = signature;
			window
				.fetch( editorConfig.restCompileUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': editorConfig.restNonce || '',
					},
					body: JSON.stringify( { classes: uniqueSorted } ),
				} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( payload ) {
					if ( payload && payload.css ) {
						injectLivePreviewStyle( payload.css );
					}
				} )
				.catch( function () {
					// Fail silently in editor preview.
				} );
		}, 400 );
	}

	/**
	 * Helper: toggle or append a utility token inside a space-separated class string.
	 */
	function toggleClassToken( currentString, tokenToToggle ) {
		const current = ( currentString || '' ).trim().split( /\s+/ ).filter( Boolean );
		const targetTokens = tokenToToggle.trim().split( /\s+/ ).filter( Boolean );
		const allPresent = targetTokens.every( function ( t ) {
			return current.indexOf( t ) !== -1;
		} );

		if ( allPresent ) {
			return current
				.filter( function ( c ) {
					return targetTokens.indexOf( c ) === -1;
				} )
				.join( ' ' );
		}
		targetTokens.forEach( function ( t ) {
			if ( current.indexOf( t ) === -1 ) {
				current.push( t );
			}
		} );
		return current.join( ' ' );
	}

	/**
	 * Universal "Tailwind Class" Inspector Control added to every Gutenberg block.
	 */
	const withTailwindInspectorControl = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			const { attributes, setAttributes, isSelected } = props;
			const [ activeBp, setActiveBp ] = useState( 'base' );
			const [ activeState, setActiveState ] = useState( 'hover' );
			const [ filterQuery, setFilterQuery ] = useState( '' );

			const editorDevice = useSelect( function ( select ) {
				const editPost = select( 'core/edit-post' );
				if ( editPost && editPost.__experimentalGetPreviewDeviceType ) {
					return editPost.__experimentalGetPreviewDeviceType();
				}
				return 'Desktop';
			}, [] );

			const editorDispatch = useDispatch( 'core/edit-post' );

			const twClasses = attributes.twClasses || '';
			const twResponsive = attributes.twResponsive || {};
			const twStates = attributes.twStates || {};

			// Trigger debounced live CSS preview whenever classes change on the selected block.
			useEffect(
				function () {
					const tokens = [];
					if ( twClasses ) {
						tokens.push.apply( tokens, twClasses.split( /\s+/ ) );
					}
					Object.keys( twResponsive ).forEach( function ( bp ) {
						( twResponsive[ bp ] || '' )
							.split( /\s+/ )
							.filter( Boolean )
							.forEach( function ( c ) {
								tokens.push( bp + ':' + c );
							} );
					} );
					Object.keys( twStates ).forEach( function ( st ) {
						( twStates[ st ] || '' )
							.split( /\s+/ )
							.filter( Boolean )
							.forEach( function ( c ) {
								tokens.push( st + ':' + c );
							} );
					} );
					if ( tokens.length ) {
						scheduleLivePreviewCompile( tokens );
					}
				},
				[ twClasses, JSON.stringify( twResponsive ), JSON.stringify( twStates ) ]
			);

			const physicalWarnings = useMemo(
				function () {
					const all = [
						twClasses,
						Object.values( twResponsive ).join( ' ' ),
						Object.values( twStates ).join( ' ' ),
					]
						.join( ' ' )
						.split( /\s+/ )
						.filter( Boolean );
					return all.filter( function ( token ) {
						return PHYSICAL_DIRECTION_REGEX.test( token );
					} );
				},
				[ twClasses, twResponsive, twStates ]
			);

			const activeBpValue = activeBp === 'base' ? twClasses : twResponsive[ activeBp ] || '';

			function updateActiveBpValue( nextVal ) {
				if ( activeBp === 'base' ) {
					setAttributes( { twClasses: nextVal } );
				} else {
					const nextResp = Object.assign( {}, twResponsive );
					nextResp[ activeBp ] = nextVal;
					setAttributes( { twResponsive: nextResp } );
				}
			}

			function updateActiveStateValue( nextVal ) {
				const nextStates = Object.assign( {}, twStates );
				nextStates[ activeState ] = nextVal;
				setAttributes( { twStates: nextStates } );
			}

			const suggestions = useMemo(
				function () {
					const list = editorConfig.autocomplete || [];
					if ( ! filterQuery.trim() ) {
						return list.slice( 0, 12 );
					}
					const q = filterQuery.trim().toLowerCase();
					return list
						.filter( function ( item ) {
							return item.toLowerCase().indexOf( q ) !== -1;
						} )
						.slice( 0, 12 );
				},
				[ filterQuery ]
			);

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				isSelected &&
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{
								title: __( 'Tailwind CSS v4 Classes (RTL-First)', 'tw-blocks' ),
								initialOpen: true,
							},
							// Responsive Breakpoint Switcher synced with Editor Preview Device
							el(
								'div',
								{ style: { marginBottom: '12px' } },
								el(
									'p',
									{ style: { margin: '0 0 6px', fontWeight: 600, fontSize: '12px' } },
									__( 'Breakpoint Scope & Device Preview:', 'tw-blocks' ) + ' (' + editorDevice + ')'
								),
								el(
									ButtonGroup,
									null,
									BREAKPOINTS.map( function ( bp ) {
										return el(
											Button,
											{
												key: bp.key,
												variant: activeBp === bp.key ? 'primary' : 'secondary',
												size: 'small',
												onClick: function () {
													setActiveBp( bp.key );
													if (
														editorDispatch &&
														editorDispatch.__experimentalSetPreviewDeviceType
													) {
														editorDispatch.__experimentalSetPreviewDeviceType( bp.device );
													}
												},
											},
											bp.label
										);
									} )
								)
							),

							// Strictly LTR Input for Tailwind Classes
							el( TextareaControl, {
								label:
									activeBp === 'base'
										? __( 'Tailwind Utility Classes (Base)', 'tw-blocks' )
										: __( 'Responsive Utilities for', 'tw-blocks' ) + ' `' + activeBp + ':`',
								help: __(
									'Always typed in LTR. Use logical properties (ms-, me-, ps-, pe-, text-start, text-end).',
									'tw-blocks'
								),
								value: activeBpValue,
								onChange: updateActiveBpValue,
								rows: 3,
								dir: 'ltr',
								style: {
									direction: 'ltr',
									textAlign: 'left',
									fontFamily: 'ui-monospace, monospace',
								},
							} ),

							// Physical Direction Warning Notice
							physicalWarnings.length > 0 &&
								el(
									Notice,
									{
										status: 'warning',
										isDismissible: false,
									},
									__(
										'Physical directional class detected! Replace ml-/mr-/pl-/pr-/left-/right-/text-left with logical ms-/me-/ps-/pe-/start-/end-/text-start:',
										'tw-blocks'
									) +
										' ' +
										physicalWarnings.join( ', ' )
								),

							// Autocomplete Search & Quick Add
							el( TextControl, {
								label: __( 'Filter Autocomplete Suggestions', 'tw-blocks' ),
								value: filterQuery,
								onChange: setFilterQuery,
								placeholder: 'ms-4, grid-cols-3, bg-primary...',
								dir: 'ltr',
							} ),
							el(
								'div',
								{
									dir: 'ltr',
									style: {
										display: 'flex',
										flexWrap: 'wrap',
										gap: '4px',
										marginBottom: '14px',
									},
								},
								suggestions.map( function ( sug ) {
									return el(
										Button,
										{
											key: sug,
											variant: 'tertiary',
											size: 'small',
											onClick: function () {
												updateActiveBpValue( toggleClassToken( activeBpValue, sug ) );
											},
										},
										'+ ' + sug
									);
								} )
							),

							// State Variants (hover:, focus:, dark:, rtl:)
							el(
								'div',
								{ style: { marginBottom: '12px' } },
								el(
									'p',
									{ style: { margin: '0 0 6px', fontWeight: 600, fontSize: '12px' } },
									__( 'Interactive State Variants (hover / focus / dark / rtl):', 'tw-blocks' )
								),
								el(
									ButtonGroup,
									null,
									STATES.map( function ( st ) {
										return el(
											Button,
											{
												key: st.key,
												variant: activeState === st.key ? 'primary' : 'secondary',
												size: 'small',
												onClick: function () {
													setActiveState( st.key );
												},
											},
											st.label
										);
									} )
								)
							),
							el( TextControl, {
								label: __( 'Utilities for State', 'tw-blocks' ) + ' `' + activeState + ':`',
								value: twStates[ activeState ] || '',
								onChange: updateActiveStateValue,
								dir: 'ltr',
								placeholder: 'opacity-90 shadow-md -scale-x-100',
							} ),

							// Visual Logical Spacing & Typography Class Pickers (writes classes, zero inline styles)
							el(
								'div',
								{ style: { marginTop: '12px' } },
								el(
									'p',
									{ style: { margin: '0 0 6px', fontWeight: 600, fontSize: '12px' } },
									__( 'Logical Spacing Presets (Writes Utility Classes):', 'tw-blocks' )
								),
								el(
									'div',
									{ dir: 'ltr', style: { display: 'flex', flexWrap: 'wrap', gap: '4px' } },
									LOGICAL_SPACING_PRESETS.map( function ( preset ) {
										return el(
											Button,
											{
												key: preset.value,
												variant: 'secondary',
												size: 'small',
												onClick: function () {
													updateActiveBpValue(
														toggleClassToken( activeBpValue, preset.value )
													);
												},
											},
											preset.label
										);
									} )
								)
							),

							el(
								'div',
								{ style: { marginTop: '12px' } },
								el(
									'p',
									{ style: { margin: '0 0 6px', fontWeight: 600, fontSize: '12px' } },
									__( 'Persian Typography & Logical Alignment:', 'tw-blocks' )
								),
								el(
									'div',
									{ dir: 'ltr', style: { display: 'flex', flexWrap: 'wrap', gap: '4px' } },
									TYPOGRAPHY_PRESETS.map( function ( preset ) {
										return el(
											Button,
											{
												key: preset.value,
												variant: 'secondary',
												size: 'small',
												onClick: function () {
													updateActiveBpValue(
														toggleClassToken( activeBpValue, preset.value )
													);
												},
											},
											preset.label
										);
									} )
								)
							),

							el(
								'div',
								{ style: { marginTop: '12px' } },
								el(
									'p',
									{ style: { margin: '0 0 6px', fontWeight: 600, fontSize: '12px' } },
									__( 'Design Token Colors & RTL Helpers:', 'tw-blocks' )
								),
								el(
									'div',
									{ dir: 'ltr', style: { display: 'flex', flexWrap: 'wrap', gap: '4px' } },
									COLOR_TOKEN_PRESETS.map( function ( preset ) {
										return el(
											Button,
											{
												key: preset.value,
												variant: 'secondary',
												size: 'small',
												onClick: function () {
													updateActiveBpValue(
														toggleClassToken( activeBpValue, preset.value )
													);
												},
											},
											preset.label
										);
									} )
								)
							)
						)
					)
			);
		};
	}, 'withTailwindInspectorControl' );

	addFilter(
		'editor.BlockEdit',
		'tw-blocks/with-tailwind-inspector-control',
		withTailwindInspectorControl
	);

	/**
	 * Register Phase 2 Blocks in the Block Editor.
	 * Uses InnerBlocks for container blocks (`section`, `grid`) and ServerSideRender + InspectorControls for leaf/dynamic blocks.
	 */
	const CONTAINER_BLOCKS = [
		{
			name: 'tw-blocks/section',
			title: __( 'Section Container', 'tw-blocks' ),
			icon: 'align-wide',
			renderInspector: function ( attributes, setAttributes ) {
				return el(
					PanelBody,
					{ title: __( 'Section Settings', 'tw-blocks' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Semantic HTML Tag', 'tw-blocks' ),
						value: attributes.tagName || 'section',
						options: [
							{ label: '<section>', value: 'section' },
							{ label: '<div>', value: 'div' },
							{ label: '<article>', value: 'article' },
							{ label: '<aside>', value: 'aside' },
							{ label: '<header>', value: 'header' },
							{ label: '<footer>', value: 'footer' },
							{ label: '<main>', value: 'main' },
							{ label: '<nav>', value: 'nav' },
						],
						onChange: function ( val ) {
							setAttributes( { tagName: val } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Max Width Token', 'tw-blocks' ),
						value: attributes.maxWidth || 'max-w-7xl',
						options: [
							{ label: 'max-w-5xl', value: 'max-w-5xl' },
							{ label: 'max-w-7xl', value: 'max-w-7xl' },
							{ label: 'max-w-full', value: 'max-w-full' },
						],
						onChange: function ( val ) {
							setAttributes( { maxWidth: val } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Background Variant', 'tw-blocks' ),
						value: attributes.bgVariant || 'surface',
						options: [
							{ label: __( 'Surface (--color-surface)', 'tw-blocks' ), value: 'surface' },
							{ label: __( 'White', 'tw-blocks' ), value: 'white' },
							{ label: __( 'Primary (--color-primary)', 'tw-blocks' ), value: 'primary' },
							{ label: __( 'Secondary (--color-secondary)', 'tw-blocks' ), value: 'secondary' },
							{ label: __( 'Transparent', 'tw-blocks' ), value: 'transparent' },
						],
						onChange: function ( val ) {
							setAttributes( { bgVariant: val } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Hide on Mobile (< md)', 'tw-blocks' ),
						checked: !! attributes.hideOnMobile,
						onChange: function ( val ) {
							setAttributes( { hideOnMobile: val } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Hide on Desktop (>= md)', 'tw-blocks' ),
						checked: !! attributes.hideOnDesktop,
						onChange: function ( val ) {
							setAttributes( { hideOnDesktop: val } );
						},
					} )
				);
			},
		},
		{
			name: 'tw-blocks/grid',
			title: __( 'Grid & Flex Layout', 'tw-blocks' ),
			icon: 'grid-view',
			renderInspector: function ( attributes, setAttributes ) {
				return el(
					PanelBody,
					{ title: __( 'Grid & Flex Settings', 'tw-blocks' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Layout Mode', 'tw-blocks' ),
						value: attributes.layoutMode || 'grid',
						options: [
							{ label: 'CSS Grid', value: 'grid' },
							{ label: 'Flexbox', value: 'flex' },
						],
						onChange: function ( val ) {
							setAttributes( { layoutMode: val } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Columns on Desktop (lg:)', 'tw-blocks' ),
						value: String( attributes.colsLg || 3 ),
						options: [
							{ label: '1', value: '1' },
							{ label: '2', value: '2' },
							{ label: '3', value: '3' },
							{ label: '4', value: '4' },
						],
						onChange: function ( val ) {
							setAttributes( { colsLg: parseInt( val, 10 ) || 3 } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Gap Token', 'tw-blocks' ),
						value: attributes.gap || 'gap-6',
						options: [
							{ label: 'gap-2', value: 'gap-2' },
							{ label: 'gap-4', value: 'gap-4' },
							{ label: 'gap-6', value: 'gap-6' },
							{ label: 'gap-8', value: 'gap-8' },
						],
						onChange: function ( val ) {
							setAttributes( { gap: val } );
						},
					} )
				);
			},
		},
	];

	CONTAINER_BLOCKS.forEach( function ( cfg ) {
		registerBlockType( cfg.name, {
			apiVersion: 3,
			title: cfg.title,
			category: 'tw-blocks',
			icon: cfg.icon,
			edit: function ( props ) {
				const blockProps = useBlockProps( {
					className: 'twb-editor-container ' + ( props.attributes.twClasses || '' ),
				} );
				return el(
					Fragment,
					null,
					el( InspectorControls, null, cfg.renderInspector( props.attributes, props.setAttributes ) ),
					el( 'div', blockProps, el( InnerBlocks, null ) )
				);
			},
			save: function () {
				return el( InnerBlocks.Content, null );
			},
		} );
	} );

	const SSR_BLOCKS = [
		{ name: 'tw-blocks/heading', title: __( 'Heading (H1–H6)', 'tw-blocks' ), icon: 'heading' },
		{ name: 'tw-blocks/text', title: __( 'Rich Text Paragraph', 'tw-blocks' ), icon: 'editor-paragraph' },
		{ name: 'tw-blocks/button', title: __( 'Action Button', 'tw-blocks' ), icon: 'button' },
		{ name: 'tw-blocks/image', title: __( 'SEO Image (Zero-CLS)', 'tw-blocks' ), icon: 'format-image' },
		{ name: 'tw-blocks/icon', title: __( 'Inline SVG Icon', 'tw-blocks' ), icon: 'star-filled' },
		{ name: 'tw-blocks/spacer', title: __( 'Vertical Spacer', 'tw-blocks' ), icon: 'sort' },
		{ name: 'tw-blocks/divider', title: __( 'Horizontal Divider', 'tw-blocks' ), icon: 'minus' },
		{ name: 'tw-blocks/faq', title: __( 'FAQ Accordion (Zero-JS + Schema)', 'tw-blocks' ), icon: 'editor-help' },
		{ name: 'tw-blocks/tabs', title: __( 'Accessible Tabs', 'tw-blocks' ), icon: 'index-card' },
		{ name: 'tw-blocks/card', title: __( 'Feature Card', 'tw-blocks' ), icon: 'id-alt' },
		{ name: 'tw-blocks/icon-box', title: __( 'Icon Box', 'tw-blocks' ), icon: 'awards' },
		{
			name: 'tw-blocks/post-grid',
			title: __( 'Dynamic Post Grid (Jalali & Persian Digits)', 'tw-blocks' ),
			icon: 'ReadMore',
		},
		{
			name: 'tw-blocks/counter',
			title: __( 'Animated Counter (Persian Digits)', 'tw-blocks' ),
			icon: 'performance',
		},
		{
			name: 'tw-blocks/toc',
			title: __( 'Dynamic Table of Contents (TOC)', 'tw-blocks' ),
			icon: 'list-view',
		},
		{
			name: 'tw-blocks/wc-sale-timer',
			title: __( 'WooCommerce Sale Badge & Countdown', 'tw-blocks' ),
			icon: 'tag',
		},
		{
			name: 'tw-blocks/wc-recently-viewed',
			title: __( 'WooCommerce Recently Viewed Products', 'tw-blocks' ),
			icon: 'visibility',
		},
	];

	SSR_BLOCKS.forEach( function ( cfg ) {
		registerBlockType( cfg.name, {
			apiVersion: 3,
			title: cfg.title,
			category: 'tw-blocks',
			icon: cfg.icon,
			edit: function ( props ) {
				const blockProps = useBlockProps();
				return el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: cfg.name,
								attributes: props.attributes,
						  } )
						: el( 'div', null, cfg.title )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );

	// Register InnerBlocks-capable blocks: custom-loop, slider, modal.
	[
		{
			name: 'tw-blocks/custom-loop',
			title: __( 'Custom Loop (WP_Query + InnerBlocks)', 'tw-blocks' ),
			icon: 'networking',
		},
		{
			name: 'tw-blocks/slider',
			title: __( 'CSS Scroll-Snap Slider (RTL)', 'tw-blocks' ),
			icon: 'slides',
		},
		{
			name: 'tw-blocks/modal',
			title: __( 'Native HTML5 Dialog Modal', 'tw-blocks' ),
			icon: 'welcome-view-site',
		},
	].forEach( function ( cfg ) {
		registerBlockType( cfg.name, {
			apiVersion: 3,
			title: cfg.title,
			category: 'tw-blocks',
			icon: cfg.icon,
			edit: function ( props ) {
				const blockProps = useBlockProps( {
					className: 'twb-editor-container ' + ( props.attributes.twClasses || '' ),
				} );
				return el( 'div', blockProps, el( InnerBlocks, null ) );
			},
			save: function () {
				return el( InnerBlocks.Content, null );
			},
		} );
	} );

	/**
	 * Persian Inspector Controls for WordPress core `core/query` block:
	 * - فقط پست‌های ویژه (`twbFeaturedOnly`)
	 * - مرتب‌سازی بر اساس بازدید یا متا (`twbOrderByMeta`)
	 * - حذف پست جاری (`twbExcludeCurrent`)
	 * - آفست / پرش از ابتدای لیست (`offset`)
	 */
	const withCoreQueryPersianControls = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== 'core/query' ) {
				return el( BlockEdit, props );
			}

			const query = props.attributes.query || {};
			function updateQueryPatch( patch ) {
				props.setAttributes( {
					query: Object.assign( {}, query, patch ),
				} );
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'تنظیمات پیشرفتهٔ کوئری (TW Blocks)', 'tw-blocks' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'فقط پست‌ها / محصولات ویژه (Featured Only)', 'tw-blocks' ),
							checked: !! query.twbFeaturedOnly,
							onChange: function ( val ) {
								updateQueryPatch( { twbFeaturedOnly: !! val } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'حذف پست جاری از لیست (Exclude Current Post)', 'tw-blocks' ),
							checked: !! query.twbExcludeCurrent,
							onChange: function ( val ) {
								updateQueryPatch( { twbExcludeCurrent: !! val } );
							},
						} ),
						el( SelectControl, {
							label: __( 'مرتب‌سازی بر اساس بازدید یا متا (Meta Sort)', 'tw-blocks' ),
							value: query.twbOrderByMeta || '',
							options: [
								{ label: __( 'پیش‌فرض (بر اساس تاریخ انتشار)', 'tw-blocks' ), value: '' },
								{ label: __( 'پربازدیدترین (_twb_views_count)', 'tw-blocks' ), value: 'views' },
								{ label: __( 'بازدید پست (post_views_count)', 'tw-blocks' ), value: 'post_views' },
								{ label: __( 'پرفروش‌ترین ووکامرس (total_sales)', 'tw-blocks' ), value: 'total_sales' },
								{ label: __( 'قیمت محصول (_price)', 'tw-blocks' ), value: 'price' },
								{ label: __( 'امتیاز کاربران (_wc_average_rating)', 'tw-blocks' ), value: 'rating' },
							],
							onChange: function ( val ) {
								updateQueryPatch( { twbOrderByMeta: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'آفست / رد کردن تعداد آیتم از ابتدا (Offset)', 'tw-blocks' ),
							type: 'number',
							value: String( query.offset || 0 ),
							onChange: function ( val ) {
								updateQueryPatch( { offset: Math.max( 0, parseInt( val || '0', 10 ) || 0 ) } );
							},
						} )
					)
				)
			);
		};
	}, 'withCoreQueryPersianControls' );

	addFilter(
		'editor.BlockEdit',
		'tw-blocks/core-query-persian-controls',
		withCoreQueryPersianControls
	);
} )( window.wp );
