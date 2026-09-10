( function (
	blocks,
	element,
	blockEditor,
	components,
	i18n,
	serverSideRender,
	presentationBlocks
) {
	'use strict';

	if (
		! blocks ||
		! element ||
		! blockEditor ||
		! components ||
		! i18n ||
		! serverSideRender ||
		! presentationBlocks
	) {
		return;
	}

	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var inheritedToggleOptions = [
		{
			label: __( 'استفاده از تنظیمات کلی', 'musicwave' ),
			value: 'inherit',
		},
		{ label: __( 'فعال', 'musicwave' ), value: 'enabled' },
		{ label: __( 'غیرفعال', 'musicwave' ), value: 'disabled' },
	];

	function queryFilteringPanel( props ) {
		function update( key, value ) {
			var attributes = {};
			attributes[ key ] = value;
			props.setAttributes( attributes );
		}

		// Help texts are deliberately verbose so a non-technical buyer never needs to open documentation.
		return createElement(
			components.PanelBody,
			{
				title: __( 'پرس‌وجو و فیلتر', 'musicwave' ),
				initialOpen: false,
			},
			createElement( components.SelectControl, {
				label: __( 'مرتب‌سازی بر اساس', 'musicwave' ),
				help: __(
					'نحوه مرتب‌سازی انتشارها پیش از نمایش.',
					'musicwave'
				),
				value: props.attributes.orderBy || 'date',
				options: [
					{
						label: __( 'تاریخ انتشار', 'musicwave' ),
						value: 'date',
					},
					{
						label: __( 'تاریخ ویرایش', 'musicwave' ),
						value: 'modified',
					},
					{ label: __( 'عنوان', 'musicwave' ), value: 'title' },
					{ label: __( 'تصادفی', 'musicwave' ), value: 'rand' },
					{
						label: __( 'پربازدیدترین', 'musicwave' ),
						value: 'views',
					},
				],
				onChange( value ) {
					update( 'orderBy', value );
				},
			} ),
			createElement( components.SelectControl, {
				label: __( 'جهت', 'musicwave' ),
				value: props.attributes.order || 'DESC',
				options: [
					{ label: __( 'نزولی', 'musicwave' ), value: 'DESC' },
					{ label: __( 'صعودی', 'musicwave' ), value: 'ASC' },
				],
				onChange( value ) {
					update( 'order', value );
				},
			} ),
			createElement( components.SelectControl, {
				label: __( 'فیلتر طبقه‌بندی', 'musicwave' ),
				help: __(
					'ویترین یا اسلایدر را به یک طبقه‌بندی محدود کنید، یا روی «همهٔ انتشارها» بگذارید.',
					'musicwave'
				),
				value: props.attributes.taxonomy || '',
				options: [
					{ label: __( 'همهٔ انتشارها', 'musicwave' ), value: '' },
					{ label: __( 'هنرمند', 'musicwave' ), value: 'mw_artist' },
					{ label: __( 'سبک', 'musicwave' ), value: 'mw_genre' },
					{ label: __( 'حال‌وهوا', 'musicwave' ), value: 'mw_mood' },
					{
						label: __( 'نوع انتشار', 'musicwave' ),
						value: 'mw_release_type',
					},
					{ label: __( 'برچسب', 'musicwave' ), value: 'mw_label' },
				],
				onChange( value ) {
					update( 'taxonomy', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'نامک عبارت', 'musicwave' ),
				help: __(
					'اختیاری. نامک را از فهرست هنرمندان / سبک‌ها / حال‌وهواها در وردپرس → MusicWave کپی کنید. اگر خالی بگذارید، همهٔ موارد نمایش داده می‌شوند. نمونه: pop یا lo-fi.',
					'musicwave'
				),
				placeholder: __( 'مثلاً pop، lo-fi، hip-hop', 'musicwave' ),
				value: props.attributes.termSlug || '',
				onChange( value ) {
					update( 'termSlug', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'نوع انتشار (نامک)', 'musicwave' ),
				help: __(
					'اختیاری. با نامک نوع انتشار مانند album، single، ep، podcast_show یا podcast_episode فیلتر کنید. برای نمایش همه انواع، خالی بگذارید. این موارد در MusicWave → انواع انتشار قرار دارند.',
					'musicwave'
				),
				placeholder: __(
					'مثلاً album، single، podcast_show',
					'musicwave'
				),
				value: props.attributes.contentType || '',
				onChange( value ) {
					update( 'contentType', value );
				},
			} ),
			createElement( components.TextControl, {
				label: __( 'شناسه انتشارهای منتخب', 'musicwave' ),
				help: __(
					'اختیاری. برای تعیین ترتیب دقیق، شناسه انتشارها را با کاما جدا کرده و وارد کنید (مثلاً ۱۲، ۴۵، ۷۸). با بردن نشانگر روی عنوان انتشار در MusicWave → انتشارها، شناسه را در پیش‌نمایش پیوند ببینید. این گزینه همه فیلترهای دیگر را نادیده می‌گیرد.',
					'musicwave'
				),
				placeholder: __( 'مثلاً ۱۲، ۴۵، ۷۸', 'musicwave' ),
				value: props.attributes.releaseIds || '',
				onChange( value ) {
					update( 'releaseIds', value );
				},
			} )
		);
	}

	function sliderInspector( props ) {
		function select( key, label ) {
			return createElement( components.SelectControl, {
				key,
				label,
				value: props.attributes[ key ] || 'inherit',
				options: inheritedToggleOptions,
				onChange( value ) {
					var update = {};
					update[ key ] = value;
					props.setAttributes( update );
				},
			} );
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{
					title: __( 'محتوای اسلایدر', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.TextControl, {
					label: __( 'برچسب بالایی', 'musicwave' ),
					value: props.attributes.eyebrow || '',
					onChange( value ) {
						props.setAttributes( { eyebrow: value } );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'عنوان', 'musicwave' ),
					value: props.attributes.title || '',
					onChange( value ) {
						props.setAttributes( { title: value } );
					},
				} ),
				createElement( components.RangeControl, {
					label: __( 'تعداد انتشارهای بارگذاری‌شده', 'musicwave' ),
					value: props.attributes.itemsToShow || 6,
					min: 3,
					max: 12,
					onChange( value ) {
						props.setAttributes( { itemsToShow: value || 6 } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'نمایش چکیده‌ها', 'musicwave' ),
					checked: !! props.attributes.showExcerpt,
					onChange( value ) {
						props.setAttributes( { showExcerpt: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'نمایش هنرمند', 'musicwave' ),
					checked: false !== props.attributes.showArtist,
					onChange( value ) {
						props.setAttributes( { showArtist: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'نمایش تاریخ', 'musicwave' ),
					checked: !! props.attributes.showDate,
					onChange( value ) {
						props.setAttributes( { showDate: !! value } );
					},
				} ),
				createElement( components.ToggleControl, {
					label: __( 'نمایش تعداد بازدید', 'musicwave' ),
					checked: !! props.attributes.showViews,
					onChange( value ) {
						props.setAttributes( { showViews: !! value } );
					},
				} )
			),
			queryFilteringPanel( props ),
			createElement(
				components.PanelBody,
				{
					title: __( 'رفتار اسلایدر', 'musicwave' ),
					initialOpen: true,
				},
				select( 'enabled', __( 'نمایش اسلایدر', 'musicwave' ) ),
				select( 'autoplay', __( 'پخش خودکار', 'musicwave' ) ),
				select( 'loop', __( 'تکرار', 'musicwave' ) ),
				select(
					'pauseOnHover',
					__( 'توقف هنگام قرارگیری نشانگر یا تمرکز', 'musicwave' )
				),
				select( 'showArrows', __( 'فلش‌های پیمایش', 'musicwave' ) ),
				select( 'showDots', __( 'نقاط صفحه‌بندی', 'musicwave' ) ),
				createElement( components.RangeControl, {
					label: __( 'فاصلهٔ پخش خودکار (میلی‌ثانیه)', 'musicwave' ),
					value: props.attributes.interval || 5000,
					min: 2000,
					max: 20000,
					step: 500,
					onChange( value ) {
						props.setAttributes( { interval: value || 5000 } );
					},
				} )
			)
		);
	}

	function shelfInspector( props ) {
		function update( key, value ) {
			var attributes = {};
			attributes[ key ] = value;
			props.setAttributes( attributes );
		}

		function toggle( key, label ) {
			return createElement( components.ToggleControl, {
				key,
				label,
				checked: false !== props.attributes[ key ],
				onChange( value ) {
					update( key, !! value );
				},
			} );
		}

		return createElement(
			blockEditor.InspectorControls,
			null,
			createElement(
				components.PanelBody,
				{
					title: __( 'محتوای ویترین', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.SelectControl, {
					label: __( 'منبع محتوا', 'musicwave' ),
					help: __(
						'ویترین را بین انتشارهای کاتالوگ و فهرست‌های پخش عمومی جامعه جابه‌جا کنید.',
						'musicwave'
					),
					value: props.attributes.source || 'releases',
					options: [
						{
							label: __( 'انتشارها', 'musicwave' ),
							value: 'releases',
						},
						{
							label: __( 'فهرست‌های پخش عمومی', 'musicwave' ),
							value: 'playlists',
						},
					],
					onChange( value ) {
						update( 'source', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'برچسب بالایی', 'musicwave' ),
					value: props.attributes.eyebrow || '',
					onChange( value ) {
						update( 'eyebrow', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'عنوان', 'musicwave' ),
					value: props.attributes.title || '',
					onChange( value ) {
						update( 'title', value );
					},
				} ),
				createElement( components.TextareaControl, {
					label: __( 'توضیحات', 'musicwave' ),
					value: props.attributes.description || '',
					onChange( value ) {
						update( 'description', value );
					},
				} ),
				createElement( components.RangeControl, {
					label:
						'playlists' ===
						( props.attributes.source || 'releases' )
							? __( 'فهرست‌های پخش برای نمایش', 'musicwave' )
							: __( 'انتشارها برای نمایش', 'musicwave' ),
					value: props.attributes.itemsToShow || 8,
					min: 1,
					max: 24,
					onChange( value ) {
						update( 'itemsToShow', value || 8 );
					},
				} )
			),
			'playlists' === ( props.attributes.source || 'releases' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'پرس‌وجوی فهرست پخش', 'musicwave' ),
							initialOpen: true,
						},
						createElement( components.SelectControl, {
							label: __(
								'مرتب‌سازی فهرست‌های پخش بر اساس',
								'musicwave'
							),
							value:
								props.attributes.playlistOrderBy ||
								'updated_at',
							options: [
								{
									label: __(
										'به‌تازگی به‌روزرسانی‌شده',
										'musicwave'
									),
									value: 'updated_at',
								},
								{
									label: __(
										'جدیدترین‌ها در ابتدا',
										'musicwave'
									),
									value: 'created_at',
								},
								{
									label: __( 'عنوان', 'musicwave' ),
									value: 'title',
								},
							],
							onChange( value ) {
								update( 'playlistOrderBy', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __(
								'فیلتر جست‌وجوی فهرست پخش',
								'musicwave'
							),
							help: __(
								'اختیاری. فقط فهرست‌های پخش عمومی را نمایش بده که عنوانشان با این متن مطابقت دارد.',
								'musicwave'
							),
							value: props.attributes.playlistSearch || '',
							onChange( value ) {
								update( 'playlistSearch', value );
							},
						} )
				  )
				: queryFilteringPanel( props ),
			createElement(
				components.PanelBody,
				{
					title: __( 'چیدمان و تصویر', 'musicwave' ),
					initialOpen: true,
				},
				createElement( components.SelectControl, {
					label: __( 'چیدمان', 'musicwave' ),
					value: props.attributes.layout || 'grid',
					options: [
						{
							label: __( 'شبکه واکنش‌گرا', 'musicwave' ),
							value: 'grid',
						},
						{
							label: __( 'ویترین افقی', 'musicwave' ),
							value: 'scroll',
						},
						{
							label: __( 'فهرست جمع‌وجور', 'musicwave' ),
							value: 'list',
						},
						{
							label: __( 'ویژهنامه سرمقاله', 'musicwave' ),
							value: 'feature',
						},
						{
							label: __( 'اسلایدر هیرو', 'musicwave' ),
							value: 'slider',
						},
						{
							label: __( 'جدول برترین‌ها (چارت)', 'musicwave' ),
							value: 'chart',
						},
					],
					onChange( value ) {
						update( 'layout', value );
					},
				} ),
				[ 'grid', 'scroll', 'list' ].includes(
					props.attributes.layout || 'grid'
				)
					? createElement( components.SelectControl, {
							label: __( 'سبک کارت', 'musicwave' ),
							help: __(
								'سبک WAVE کارت‌ها را روی سطح پدینگ‌دار با نشان کیفیت و خط متا نمایش می‌دهد.',
								'musicwave'
							),
							value: props.attributes.cardStyle || 'classic',
							options: [
								{
									label: __( 'کلاسیک', 'musicwave' ),
									value: 'classic',
								},
								{
									label: __( 'WAVE (کاشی سرمقاله)', 'musicwave' ),
									value: 'wave',
								},
							],
							onChange( value ) {
								update( 'cardStyle', value );
							},
					  } )
					: null,
				'slider' === ( props.attributes.layout || 'grid' )
					? createElement( components.SelectControl, {
							label: __( 'سبک هیرو', 'musicwave' ),
							value: props.attributes.heroStyle || 'cinematic',
							options: [
								{
									label: __( 'سینمایی (تمام‌عرض)', 'musicwave' ),
									value: 'cinematic',
								},
								{
									label: __( 'سرمقاله (کارت + وینیل)', 'musicwave' ),
									value: 'editorial',
								},
							],
							onChange( value ) {
								update( 'heroStyle', value );
							},
					  } )
					: null,
				createElement( components.RangeControl, {
					label: __( 'ستون‌های دسکتاپ', 'musicwave' ),
					value: props.attributes.columns || 4,
					min: 2,
					max: 6,
					onChange( value ) {
						update( 'columns', value || 4 );
					},
				} ),
				createElement( components.SelectControl, {
					label: __( 'شکل تصویر', 'musicwave' ),
					value: props.attributes.imageShape || 'square',
					options: [
						{ label: __( 'مربع', 'musicwave' ), value: 'square' },
						{
							label: __( 'افقی', 'musicwave' ),
							value: 'landscape',
						},
						{
							label: __( 'عمودی', 'musicwave' ),
							value: 'portrait',
						},
						{ label: __( 'دایره', 'musicwave' ), value: 'circle' },
					],
					onChange( value ) {
						update( 'imageShape', value );
					},
				} ),
				toggle( 'showArtwork', __( 'نمایش تصویر', 'musicwave' ) ),
				toggle(
					'showPlayButton',
					__( 'نمایش کنترل پخش', 'musicwave' )
				),
				toggle( 'showArtist', __( 'نمایش هنرمند', 'musicwave' ) ),
				toggle( 'showDate', __( 'نمایش تاریخ', 'musicwave' ) ),
				toggle( 'showExcerpt', __( 'نمایش چکیده', 'musicwave' ) ),
				toggle( 'showAction', __( 'نمایش پیوند اقدام', 'musicwave' ) ),
				toggle( 'showBadge', __( 'نمایش نشان کیفیت (Hi-Res/FLAC)', 'musicwave' ) ),
				toggle( 'showMeta', __( 'نمایش خط متا (نوع · سال)', 'musicwave' ) ),
				createElement( components.TextControl, {
					label: __( 'برچسب اقدام', 'musicwave' ),
					value: props.attributes.actionLabel || '',
					onChange( value ) {
						update( 'actionLabel', value );
					},
				} )
			),
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'ویژه‌نامه سرمقاله', 'musicwave' ),
							initialOpen: true,
						},
						createElement( components.RangeControl, {
							label: __(
								'شناسه انتشار منتخب (۰ = اولین نتیجه)',
								'musicwave'
							),
							value: props.attributes.featuredReleaseId || 0,
							min: 0,
							max: 99999,
							onChange( value ) {
								update( 'featuredReleaseId', value || 0 );
							},
						} ),
						createElement( components.SelectControl, {
							label: __( 'متن بخش منتخب', 'musicwave' ),
							value: props.attributes.featuredSource || 'excerpt',
							options: [
								{
									label: __(
										'استفاده از چکیده',
										'musicwave'
									),
									value: 'excerpt',
								},
								{
									label: __(
										'استفاده از فیلد توضیحات',
										'musicwave'
									),
									value: 'custom',
								},
								{
									label: __( 'بدون متن بدنه', 'musicwave' ),
									value: 'none',
								},
							],
							onChange( value ) {
								update( 'featuredSource', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __( 'تیرگی لایه رویی', 'musicwave' ),
							value:
								props.attributes.overlay !== undefined
									? props.attributes.overlay
									: 50,
							min: 0,
							max: 100,
							onChange( value ) {
								update(
									'overlay',
									value !== undefined ? value : 50
								);
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'نمایش شماره رتبه', 'musicwave' ),
							checked: !! props.attributes.showRank,
							onChange( value ) {
								update( 'showRank', !! value );
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'نمایش تعداد بازدید', 'musicwave' ),
							checked: !! props.attributes.showViews,
							onChange( value ) {
								update( 'showViews', !! value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __( 'عرض ستون کناری (پیکسل)', 'musicwave' ),
							value: props.attributes.sideColumnWidth || 340,
							min: 200,
							max: 560,
							onChange( value ) {
								update( 'sideColumnWidth', value || 340 );
							},
						} )
				  )
				: null,
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'عنوان بخش معرفی', 'musicwave' ),
							initialOpen: false,
						},
						createElement( components.TextControl, {
							label: __( 'رنگ عنوان (هگز)', 'musicwave' ),
							help: __( 'مثلاً #ffffff', 'musicwave' ),
							value: props.attributes.heroTitleColor || '',
							onChange( value ) {
								update( 'heroTitleColor', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __(
								'اندازه فونت عنوان (پیکسل، ۰ = پیش‌فرض)',
								'musicwave'
							),
							value: props.attributes.heroTitleSize || 0,
							min: 0,
							max: 96,
							onChange( value ) {
								update( 'heroTitleSize', value || 0 );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'رنگ متن بدنه (هگز)', 'musicwave' ),
							help: __( 'مثلاً #cccccc', 'musicwave' ),
							value: props.attributes.heroTextColor || '',
							onChange( value ) {
								update( 'heroTextColor', value );
							},
						} )
				  )
				: null,
			'feature' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'دکمه فراخوان اقدام', 'musicwave' ),
							initialOpen: false,
						},
						createElement( components.TextControl, {
							label: __( 'برچسب دکمه', 'musicwave' ),
							value: props.attributes.ctaLabel || '',
							onChange( value ) {
								update( 'ctaLabel', value );
							},
						} ),
						createElement( components.SelectControl, {
							label: __( 'سبک دکمه', 'musicwave' ),
							value: props.attributes.ctaStyle || 'solid',
							options: [
								{
									label: __( 'پرشدگی یکدست', 'musicwave' ),
									value: 'solid',
								},
								{
									label: __( 'خطی', 'musicwave' ),
									value: 'outline',
								},
								{
									label: __( 'شفاف', 'musicwave' ),
									value: 'ghost',
								},
							],
							onChange( value ) {
								update( 'ctaStyle', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'پس‌زمینه دکمه (هگز)', 'musicwave' ),
							help: __(
								'برای استفاده از رنگ تأکیدی قالب، خالی بگذارید.',
								'musicwave'
							),
							value: props.attributes.ctaBgColor || '',
							onChange( value ) {
								update( 'ctaBgColor', value );
							},
						} ),
						createElement( components.TextControl, {
							label: __( 'رنگ متن دکمه (هگز)', 'musicwave' ),
							help: __(
								'برای استفاده از مقدار پیش‌فرض قالب، خالی بگذارید.',
								'musicwave'
							),
							value: props.attributes.ctaTextColor || '',
							onChange( value ) {
								update( 'ctaTextColor', value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __( 'شعاع گوشه دکمه (پیکسل)', 'musicwave' ),
							value:
								props.attributes.ctaRadius !== undefined
									? props.attributes.ctaRadius
									: 999,
							min: 0,
							max: 999,
							onChange( value ) {
								update(
									'ctaRadius',
									value !== undefined ? value : 999
								);
							},
						} )
				  )
				: null,
			'slider' === ( props.attributes.layout || 'grid' )
				? createElement(
						components.PanelBody,
						{
							title: __( 'تنظیمات اسلایدر هیرو', 'musicwave' ),
							initialOpen: true,
						},
						createElement( components.ToggleControl, {
							label: __( 'پخش خودکار اسلایدها', 'musicwave' ),
							checked:
								props.attributes.autoplay === undefined
									? true
									: !! props.attributes.autoplay,
							onChange( value ) {
								update( 'autoplay', !! value );
							},
						} ),
						createElement( components.RangeControl, {
							label: __(
								'فاصلهٔ تعویض اسلایدها (میلی‌ثانیه)',
								'musicwave'
							),
							value:
								props.attributes.interval !== undefined
									? props.attributes.interval
									: 5000,
							min: 2000,
							max: 20000,
							onChange( value ) {
								update(
									'interval',
									value !== undefined ? value : 5000
								);
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'نمایش فلش‌ها', 'musicwave' ),
							checked:
								props.attributes.showArrows === undefined
									? true
									: !! props.attributes.showArrows,
							onChange( value ) {
								update( 'showArrows', !! value );
							},
						} ),
						createElement( components.ToggleControl, {
							label: __( 'نمایش نقاط صفحهبندی', 'musicwave' ),
							checked:
								props.attributes.showDots === undefined
									? true
									: !! props.attributes.showDots,
							onChange( value ) {
								update( 'showDots', !! value );
							},
						} )
				  )
				: null,
			createElement(
				components.PanelBody,
				{
					title: __( 'پیوند بخش', 'musicwave' ),
					initialOpen: false,
				},
				createElement( blockEditor.URLInput, {
					label: __( 'پیوند مشاهده همه', 'musicwave' ),
					value: props.attributes.sectionUrl || '',
					onChange( value ) {
						update( 'sectionUrl', value );
					},
				} ),
				createElement( components.TextControl, {
					label: __( 'برچسب مشاهده همه', 'musicwave' ),
					value: props.attributes.sectionLinkLabel || '',
					onChange( value ) {
						update( 'sectionLinkLabel', value );
					},
				} )
			)
		);
	}

	presentationBlocks.forEach( function ( block ) {
		if ( blocks.getBlockType( block.name ) ) {
			return;
		}

		var textdomain = block.textdomain || 'musicwave';
		// Metadata originates in block.json and is intentionally translated at runtime.
		// eslint-disable-next-line @wordpress/i18n-no-variables
		var title = __( block.title, textdomain );
		// eslint-disable-next-line @wordpress/i18n-no-variables
		var description = __( block.description, textdomain );
		var keywords = ( block.keywords || [] ).map( function ( keyword ) {
			// eslint-disable-next-line @wordpress/i18n-no-variables
			return __( keyword, textdomain );
		} );

		blocks.registerBlockType( block.name, {
			apiVersion: block.apiVersion || 3,
			title,
			description,
			category: block.category || 'music-wave',
			icon: block.icon,
			keywords,
			textdomain,
			attributes: block.attributes || {},
			supports: block.supports || {},
			example: block.example || {},
			edit( props ) {
				var preview = createElement( serverSideRender, {
					block: block.name,
					attributes: props.attributes,
					EmptyResponsePlaceholder() {
						return createElement( components.Placeholder, {
							icon: block.icon,
							label: title,
							instructions: __(
								'برای نمایش پیش‌نمایش زنده، انتشار منتشرشده اضافه کنید یا این بلوک را فعال کنید.',
								'musicwave'
							),
						} );
					},
				} );

				if (
					'music-wave/release-slider' === block.name ||
					'musicwave/release-slider' === block.name
				) {
					return createElement(
						Fragment,
						null,
						sliderInspector( props ),
						preview
					);
				}

				if (
					'music-wave/release-shelf' === block.name ||
					'musicwave/release-shelf' === block.name
				) {
					return createElement(
						Fragment,
						null,
						shelfInspector( props ),
						preview
					);
				}

				return preview;
			},
			save() {
				return null;
			},
		} );
	} );
} )(
	window.wp && window.wp.blocks,
	window.wp && window.wp.element,
	window.wp && window.wp.blockEditor,
	window.wp && window.wp.components,
	window.wp && window.wp.i18n,
	window.wp && window.wp.serverSideRender,
	window.musicwavePresentationBlocks || []
);
