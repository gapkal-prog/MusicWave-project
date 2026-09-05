/**
 * Editor counterparts for the PHP-rendered MusicWave blocks.
 *
 * @package
 */
( function (
	blocks,
	element,
	blockEditor,
	components,
	i18n,
	serverSideRender,
	dynamicBlocks
) {
	'use strict';

	if (
		! blocks ||
		! element ||
		! blockEditor ||
		! components ||
		! i18n ||
		! serverSideRender ||
		! dynamicBlocks
	) {
		return;
	}

	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	// Always-callable data selector: on legacy WordPress builds without the
	// data store the callback runs with a null select and helpers degrade to
	// their defaults, while hooks keep an unconditional call order.
	var useSelect =
		window.wp && window.wp.data && window.wp.data.useSelect
			? window.wp.data.useSelect
			: function ( callback ) {
					return callback( null );
			  };

	var fieldConfig = {
		'music-wave/release-meta': {
			releaseId: true,
			compact: true,
			groups: [
				{
					title: __( 'فیلدها', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showCatalogNumber',
							__( 'نمایش شماره کاتالوگ', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showReleaseDate',
							__( 'نمایش تاریخ انتشار', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showDuration',
							__( 'نمایش مدت زمان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showBpm',
							__( 'نمایش BPM', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showKey',
							__( 'نمایش کلید موسیقی', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtist',
							__( 'نمایش هنرمند', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showGenre',
							__( 'نمایش ژانر', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showMood',
							__( 'نمایش حال و هوا', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showLabel',
							__( 'نمایش برچسب ناشر', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showReleaseType',
							__( 'نمایش نوع انتشار', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showLibraryButton',
							__(
								'نمایش دکمه افزودنی به کتابخانه',
								'music-wave-core'
							),
							true,
						],
						[
							'toggle',
							'showTaxonomyChips',
							__(
								'نمایش برچسب‌های طبقه‌بندی',
								'music-wave-core'
							),
							true,
						],
						[
							'toggle',
							'showActions',
							__(
								'نمایش اقدامات کتابخانه و فهرست پخش',
								'music-wave-core'
							),
							true,
						],
					],
					help: __(
						'فیلدهای بدون مقدار در انتشار انتخاب‌شده به‌طور خودکار پنهان می‌شوند.',
						'music-wave-core'
					),
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان فراداده', 'music-wave-core' ),
							[
								[
									'grid',
									__(
										'شبکه (کارت‌های برچسب و ارزش)',
										'music-wave-core'
									),
								],
								[
									'inline',
									__(
										'خطی (یک ردیف روان)',
										'music-wave-core'
									),
								],
								[
									'stack',
									__(
										'ردیف‌های پشته ای (برچسب در کنار مقدار)',
										'music-wave-core'
									),
								],
							],
							__(
								'ردیف‌های درون‌خطی و روی‌هم‌چیده نیز در پنل سبک‌های بلوک در دسترس هستند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showLabels',
							__( 'نمایش برچسب‌های فیلد', 'music-wave-core' ),
							true,
							__(
								'برچسب‌های پنهان در دسترس صفحه‌خوان‌ها باقی می‌مانند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'linkTerms',
							__(
								'هنرمندان، ژانرها، حال‌وهواها، و انواع را به آرشیو آن‌ها پیوند دهید',
								'music-wave-core'
							),
							false,
						],
					],
				},
			],
		},
		'music-wave/access-panel': {
			releaseId: true,
			groups: [
				{
					title: __( 'نمایش', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان پنل', 'music-wave-core' ),
							[
								[
									'banner',
									__(
										'بنر (پیام و اقدام در یک ردیف)',
										'music-wave-core'
									),
								],
								[
									'stack',
									__(
										'روی‌هم‌چیده (اقدام زیر پیام)',
										'music-wave-core'
									),
								],
							],
						],
						[
							'toggle',
							'showWhenGranted',
							__(
								'نمایش وضعیت دسترسی اعطاشده',
								'music-wave-core'
							),
							true,
							__(
								'وقتی خاموش است، بلوک برای بازدیدکنندگانی که از قبل دسترسی دارند پنهان می‌ماند.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'جایگزینی پیام‌ها', 'music-wave-core' ),
					controls: [
						[
							'text',
							'grantedMessage',
							__( 'پیام دسترسی اعطاشده', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'restrictedMessage',
							__( 'پیام دسترسی محدودشده', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'purchaseMessage',
							__( 'پیام خرید', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'purchaseCtaLabel',
							__( 'برچسب دکمه خرید', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'membershipMessage',
							__( 'پیام عضویت', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'membershipCtaLabel',
							__( 'برچسب دکمه عضویت', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'membershipCtaUrl',
							__( 'نشانی دکمهٔ عضویت', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای استفاده از پیام یا تنظیم جهانی MusicWave، خالی بگذارید.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/release-credits': {
			releaseId: true,
			groups: [
				{
					title: __( 'محتوا', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان بخش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showRole',
							__( 'نمایش نقش عوامل', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'groupByRole',
							__(
								'گروه‌بندی عوامل بر اساس نقش',
								'music-wave-core'
							),
							false,
						],
					],
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان عوامل', 'music-wave-core' ),
							[
								[ 'list', __( 'فهرست', 'music-wave-core' ) ],
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[
									'inline',
									__( 'درون‌خطی', 'music-wave-core' ),
								],
							],
						],
					],
				},
			],
		},
		'music-wave/collection-list': {
			releaseId: true,
			groups: [
				{
					title: __( 'محتوا', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان بخش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showPosition',
							__( 'نمایش شماره قطعه', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtwork',
							__( 'نمایش تصاویر جلد قطعه', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showDuration',
							__( 'نمایش مدت زمان قطعه', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showTotalDuration',
							__( 'نمایش کل زمان اجرا', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'groupByDisc',
							__(
								'گروه‌بندی قطعه‌ها بر اساس دیسک',
								'music-wave-core'
							),
							false,
							__(
								'زمانی اعمال می‌شود که هر قطعه دارای شماره دیسک باشد و مجموعه شامل چندین دیسک باشد.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'پیگیری اقدامات', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showPreview',
							__( 'نمایش دکمه‌های پیش‌نمایش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showDownload',
							__(
								'نمایش دکمه‌های دانلود امن',
								'music-wave-core'
							),
							true,
						],
					],
				},
			],
		},
		'music-wave/catalog-filters': {
			groups: [
				{
					title: __( 'فیلدهای فیلتر', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showSearch',
							__( 'نمایش فیلد جست‌وجو', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtistFilter',
							__( 'نمایش فیلتر هنرمند', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showGenreFilter',
							__( 'نمایش فیلتر ژانر', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showMoodFilter',
							__( 'نمایش فیلتر حال‌وهوا', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showTypeFilter',
							__( 'نمایش فیلتر نوع انتشار', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showSort',
							__( 'نمایش انتخابگر مرتب‌سازی', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showReset',
							__( 'نمایش پیوند بازنشانی', 'music-wave-core' ),
							true,
						],
					],
					help: __(
						'کشویی بدون عبارات منتشرشده به‌طور خودکار پنهان می‌شود.',
						'music-wave-core'
					),
				},
				{
					title: __( 'چیدمان و برچسب‌ها', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان فیلترها', 'music-wave-core' ),
							[
								[
									'inline',
									__( 'نوار درون‌خطی', 'music-wave-core' ),
								],
								[
									'stacked',
									__(
										'ردیف‌های تمام عرض',
										'music-wave-core'
									),
								],
							],
							__(
								'حالت روی‌هم‌چیده نیز در پنل سبک‌های بلوک در دسترس است.',
								'music-wave-core'
							),
						],
						[
							'range',
							'maxTerms',
							__( 'گزینه‌های هر فیلتر', 'music-wave-core' ),
							10,
							200,
							__(
								'تعداد اصطلاحات را در هر فهرست کشویی محدود می‌کند.',
								'music-wave-core'
							),
							50,
						],
						[
							'text',
							'searchPlaceholder',
							__( 'متن جایگزین جست‌وجو', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'submitLabel',
							__( 'اعمال برچسب دکمه', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'resetLabel',
							__( 'بازنشانی برچسب پیوند', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای استفاده از پیش‌فرض‌های ترجمه‌شده، برچسب‌ها را خالی بگذارید. مرتب‌سازی پیش‌فرض از تنظیمات بایگانی MusicWave پیروی می‌کند.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/catalog-results': {
			toggles: [
				[
					'showCount',
					__( 'نمایش تعداد نتایج', 'music-wave-core' ),
					true,
					__(
						'نشان می‌دهد که چند انتشار با فیلترهای فعلی مطابقت دارند.',
						'music-wave-core'
					),
				],
				[
					'showChips',
					__( 'نمایش تراشه‌های فیلتر فعال', 'music-wave-core' ),
					true,
					__(
						'هر تراشه بدون آن فیلتر به کاتالوگ بازمی‌گردد.',
						'music-wave-core'
					),
				],
			],
		},
		'music-wave/preview-player': {
			releaseId: true,
			textFields: [ [ 'label', __( 'برچسب دکمه', 'music-wave-core' ) ] ],
			groups: [
				{
					title: __( 'ظاهر', 'music-wave-core' ),
					controls: [
						[
							'select',
							'style',
							__( 'سبک دکمه', 'music-wave-core' ),
							[
								[ 'solid', __( 'جامد', 'music-wave-core' ) ],
								[
									'outline',
									__( 'طرح کلی', 'music-wave-core' ),
								],
								[
									'ghost',
									__( 'بی‌زمینه', 'music-wave-core' ),
								],
							],
							__(
								'همین گزینه‌ها در پنل سبک‌های بلوک نیز در دسترس هستند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showIcon',
							__( 'نمایش نماد پخش', 'music-wave-core' ),
							true,
							__(
								'این نماد در صورت پنهان‌شدن در دسترس فناوری کمکی قرار می‌گیرد.',
								'music-wave-core'
							),
						],
					],
				},
			],
		},
		'music-wave/download-button': {
			releaseId: true,
			compact: true,
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'description',
							__( 'توضیحات بخش', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showDescription',
							__( 'نمایش توضیحات', 'music-wave-core' ),
							true,
						],
					],
				},
				{
					title: __( 'دانلود ردیف', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showQuality',
							__( 'نمایش انتخابگر کیفیت', 'music-wave-core' ),
							true,
							__(
								'وقتی مخفی شود، هر فایل اولین کیفیت فهرست‌شده خود را دانلود می‌کند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showStream',
							__( 'نمایش دکمه‌های پخش امن', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'downloadLabel',
							__( 'برچسب دکمه دانلود', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'playLabel',
							__( 'برچسب دکمه پخش', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'loginLabel',
							__( 'برچسب دکمه ورود', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای استفاده از پیش‌فرض ترجمه‌شده، یک برچسب خالی بگذارید.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/related-releases': {
			releaseId: true,
			groups: [
				{
					title: __( 'بخش‌ها', 'music-wave-core' ),
					controls: [
						[
							'select',
							'sameArtistSection',
							__( 'بخش همان هنرمند', 'music-wave-core' ),
							[
								[
									'inherit',
									__(
										'پیش‌فرض (تنظیم جهانی)',
										'music-wave-core'
									),
								],
								[
									'enabled',
									__( 'همیشه نشان دهید', 'music-wave-core' ),
								],
								[
									'disabled',
									__( 'پنهان کردن', 'music-wave-core' ),
								],
							],
						],
						[
							'select',
							'similarSection',
							__( 'بخش انتشارات مشابه', 'music-wave-core' ),
							[
								[
									'inherit',
									__(
										'پیش‌فرض (تنظیم جهانی)',
										'music-wave-core'
									),
								],
								[
									'enabled',
									__( 'همیشه نشان دهید', 'music-wave-core' ),
								],
								[
									'disabled',
									__( 'پنهان کردن', 'music-wave-core' ),
								],
							],
						],
						[
							'text',
							'sameArtistHeading',
							__( 'عنوان همان هنرمند', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'similarHeading',
							__( 'عنوان انتشارهای مشابه', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showSectionLink',
							__( 'نمایش پیوند بخش', 'music-wave-core' ),
							false,
							__(
								'بخش مربوط به همان هنرمند را به آرشیو هنرمند و بخش مشابه را به کاتالوگ پیوند می‌دهد.',
								'music-wave-core'
							),
						],
						[
							'text',
							'sectionLinkLabel',
							__( 'برچسب پیوند بخش', 'music-wave-core' ),
							'',
						],
					],
				},
				{
					title: __( 'پرس‌وجو', 'music-wave-core' ),
					controls: [
						[
							'range',
							'itemsToShow',
							__( 'موارد در هر بخش', 'music-wave-core' ),
							2,
							12,
							__(
								'تا زمان تغییر از پیش‌فرض جهانی استفاده می‌کند.',
								'music-wave-core'
							),
							4,
						],
						[
							'select',
							'orderBy',
							__( 'به ترتیب', 'music-wave-core' ),
							[
								[
									'date',
									__( 'تاریخ انتشار', 'music-wave-core' ),
								],
								[
									'modified',
									__(
										'به‌تازگی به‌روزشده',
										'music-wave-core'
									),
								],
								[ 'title', __( 'عنوان', 'music-wave-core' ) ],
								[ 'rand', __( 'تصادفی', 'music-wave-core' ) ],
							],
						],
						[
							'select',
							'order',
							__( 'سفارش', 'music-wave-core' ),
							[
								[ 'DESC', __( 'نزولی', 'music-wave-core' ) ],
								[ 'ASC', __( 'صعودی', 'music-wave-core' ) ],
							],
						],
						[
							'toggle',
							'matchGenre',
							__( 'مطابقت با ژانرهای مشترک', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'matchMood',
							__( 'تطبیق حال‌وهواهای مشترک', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'matchType',
							__( 'مطابقت با نوع انتشار', 'music-wave-core' ),
							true,
						],
					],
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[
									'scroll',
									__( 'طومار افقی', 'music-wave-core' ),
								],
								[ 'list', __( 'فهرست', 'music-wave-core' ) ],
							],
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							4,
						],
						[
							'select',
							'imageShape',
							__( 'شکل اثر هنری', 'music-wave-core' ),
							[
								[ 'square', __( 'مربع', 'music-wave-core' ) ],
								[
									'landscape',
									__( 'منظره', 'music-wave-core' ),
								],
								[
									'portrait',
									__( 'پرتره', 'music-wave-core' ),
								],
								[ 'circle', __( 'دایره', 'music-wave-core' ) ],
							],
						],
					],
				},
				{
					title: __( 'محتوای کارت', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showArtwork',
							__( 'نمایش آثار هنری', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtist',
							__( 'نمایش هنرمند', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showDate',
							__( 'نمایش تاریخ انتشار', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showExcerpt',
							__( 'نمایش گزیده', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showPreview',
							__( 'نمایش دکمه پیش‌نمایش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showAction',
							__( 'نمایش پیوند اقدام', 'music-wave-core' ),
							false,
						],
						[
							'text',
							'actionLabel',
							__( 'برچسب پیوند اقدام', 'music-wave-core' ),
							'',
						],
					],
				},
			],
		},
		'music-wave/preview-button': {
			releaseId: true,
			compact: true,
			textFields: [ [ 'label', __( 'برچسب دکمه', 'music-wave-core' ) ] ],
			groups: [
				{
					title: __( 'ظاهر', 'music-wave-core' ),
					controls: [
						[
							'select',
							'style',
							__( 'سبک دکمه', 'music-wave-core' ),
							[
								[ 'solid', __( 'جامد', 'music-wave-core' ) ],
								[
									'outline',
									__( 'طرح کلی', 'music-wave-core' ),
								],
								[
									'ghost',
									__( 'بی‌زمینه', 'music-wave-core' ),
								],
							],
							__(
								'همین گزینه‌ها در پنل سبک‌های بلوک نیز در دسترس هستند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showIcon',
							__( 'نمایش نماد پخش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'fullWidth',
							__( 'دکمه عرض کامل', 'music-wave-core' ),
							false,
							__(
								'دکمه را کشیده تا ظرف آن پر شود.',
								'music-wave-core'
							),
						],
					],
				},
			],
		},
		'music-wave/account-dashboard': {
			groups: [
				{
					title: __( 'بخش‌ها', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showLibrary',
							__( 'پنل کتابخانه موسیقی', 'music-wave-core' ),
							true,
							__(
								'انتشارهای ذخیره‌شده به علاوه بر هر بارگیری حفاظت‌شده‌ای که بازدیدکننده حق آن را دارد.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showMembershipPanel',
							__( 'پنل عضویت', 'music-wave-core' ),
							true,
							__(
								'سطوح فعال با انقضا و محصولات طرح VIP قابل خرید. فقط زمانی که MusicWave VIP فعال است ظاهر می‌شود.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showOrders',
							__( 'پنل سفارشات', 'music-wave-core' ),
							true,
							__(
								'تاریخچه سفارش WooCommerce و نمایش‌های تک سفارشی.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showDownloads',
							__( 'پنل دانلودها', 'music-wave-core' ),
							true,
							__(
								'فایل‌های پیوست‌شده به خریدهای WooCommerce.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showAddresses',
							__( 'پنل آدرس‌ها', 'music-wave-core' ),
							true,
							__(
								'فرم‌های آدرس صورتحساب و حمل‌ونقل قابل ویرایش.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showPaymentMethods',
							__( 'پنل روش‌های پرداخت', 'music-wave-core' ),
							true,
							__(
								'کارت‌های ذخیره‌شده و روش‌های پرداخت.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showAccountDetails',
							__( 'پنل مشخصات حساب', 'music-wave-core' ),
							true,
							__(
								'فرم واقعی نام، ایمیل و رمز عبور قابل ویرایش.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showSignOut',
							__( 'میانبر خروج از سیستم', 'music-wave-core' ),
							true,
						],
					],
					help: __(
						'پنل‌های WooCommerce فقط زمانی ظاهر می‌شوند که WooCommerce فعال است. پنل عضویت به MusicWave VIP نیاز دارد.',
						'music-wave-core'
					),
				},
				{
					title: __( 'سفارش و سبک پنل', 'music-wave-core' ),
					controls: [
						[
							'select',
							'panelOrder',
							__( 'سفارش پنل', 'music-wave-core' ),
							[
								[
									'default',
									__(
										'موسیقی اول (پیش‌فرض)',
										'music-wave-core'
									),
								],
								[
									'commerce_first',
									__( 'تجارت اول', 'music-wave-core' ),
								],
								[
									'membership_first',
									__( 'ابتدا عضویت', 'music-wave-core' ),
								],
							],
							__(
								'فقط ترتیب زبانه‌ها را تغییر می‌دهد؛ همهٔ پنل‌های فعال همچنان در دسترس هستند. حالت زبانه‌ای یا روی‌هم‌چیده را از پنل سبک‌ها انتخاب کنید.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showStats',
							__( 'ردیف آمار', 'music-wave-core' ),
							true,
							__(
								'تعداد دانلودهای موجود، انتشارهای ذخیره‌شده، و هنرمندانی که دنبال می‌شوند را می‌شمارد.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showQuickLinks',
							__( 'نوار برگه', 'music-wave-core' ),
							true,
							__(
								'وقتی نوار زبانه پنهان باشد، پنل‌ها به‌صورت یک صفحهٔ پیوسته نمایش داده می‌شوند (مناسب برای سبک روی‌هم‌چیده).',
								'music-wave-core'
							),
						],
						[
							'text',
							'introText',
							__( 'متن خوش آمدگویی', 'music-wave-core' ),
							'',
							__(
								'زیر نام مشتری نشان داده شده است. برای تبریک پیش‌فرض خالی بگذارید.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'عناوین پنل‌ها', 'music-wave-core' ),
					controls: [
						[
							'text',
							'libraryHeading',
							__( 'عنوان کتابخانه موسیقی', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'membershipHeading',
							__( 'عنوان عضویت', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'ordersHeading',
							__( 'سرفصل سفارشات', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'downloadsHeading',
							__( 'عنوان دانلودها', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'addressesHeading',
							__( 'عنوان آدرس‌ها', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'paymentHeading',
							__( 'عنوان روش‌های پرداخت', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'accountHeading',
							__( 'سرفصل جزئیات حساب', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'playlistsHeading',
							__( 'عنوان فهرست‌های پخش', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'notificationsHeading',
							__( 'عنوان اعلان‌ها', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای استفاده از برچسب پیش‌فرض MusicWave ترجمه‌شده، یک فیلد خالی بگذارید.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/membership-panel': {
			toggles: [
				[
					'showActive',
					__( 'نمایش سطوح فعال و انقضا', 'music-wave-core' ),
					true,
					__(
						'کمک هزینه عضویت فعلی بیننده را فهرست می‌کند.',
						'music-wave-core'
					),
				],
				[
					'showPlans',
					__( 'نمایش محصولات طرح', 'music-wave-core' ),
					true,
					__(
						'محصولات برنامه VIP پیکربندی‌شده WooCommerce را فهرست می‌کند.',
						'music-wave-core'
					),
				],
				[
					'showBuyButtons',
					__( 'نمایش دکمه‌های خرید', 'music-wave-core' ),
					true,
					__(
						'یک دکمه افزودن به سبد خرید را به هر محصول طرح اضافه می‌کند.',
						'music-wave-core'
					),
				],
			],
			textFields: [
				[ 'heading', __( 'عنوان بخش', 'music-wave-core' ) ],
				[ 'emptyText', __( 'پیام حالت خالی', 'music-wave-core' ) ],
			],
		},
		'music-wave/music-library': {
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'intro',
							__( 'متن مقدمه', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showFilters',
							__( 'نمایش زبانه‌های فیلتر', 'music-wave-core' ),
							true,
							__(
								'زبانه‌ها موارد ذخیره‌شده را بر اساس نوع انتشار و هنرمندان دنبال‌شده گروه‌بندی می‌کنند.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showCounts',
							__( 'نمایش تعداد اقلام', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای استفاده از پیش‌فرض‌های ترجمه‌شده، متون را خالی بگذارید.',
						'music-wave-core'
					),
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'طرح کتابخانه', 'music-wave-core' ),
							[
								[ 'list', __( 'فهرست', 'music-wave-core' ) ],
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
							],
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							4,
						],
						[
							'range',
							'itemsToShow',
							__( 'موارد در هر صفحه', 'music-wave-core' ),
							1,
							100,
							undefined,
							24,
						],
					],
				},
				{
					title: __( 'نمایش آیتم', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showArtist',
							__( 'نمایش نام هنرمند', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showType',
							__( 'نمایش نوع نشان', 'music-wave-core' ),
							true,
							__(
								'آهنگ، آلبوم، پادکست یا برچسب هنرمند را نمایش می‌دهد.',
								'music-wave-core'
							),
						],
						[
							'toggle',
							'showYear',
							__( 'نمایش سال انتشار', 'music-wave-core' ),
							false,
						],
						[
							'toggle',
							'showRemove',
							__( 'نمایش دکمه‌های حذف', 'music-wave-core' ),
							true,
						],
					],
				},
			],
		},
		'music-wave/library-button': {
			releaseId: true,
			compact: true,
			groups: [
				{
					title: __( 'دکمه', 'music-wave-core' ),
					controls: [
						[
							'select',
							'itemType',
							__(
								'چیزی که دکمه ذخیره می‌کند',
								'music-wave-core'
							),
							[
								[
									'release',
									__(
										'افزودن به کتابخانه',
										'music-wave-core'
									),
								],
								[
									'wishlist',
									__(
										'افزودن به فهرست علاقه‌مندی‌ها',
										'music-wave-core'
									),
								],
								[
									'presave',
									__(
										'پیش ذخیره (فقط انتشارهای آینده)',
										'music-wave-core'
									),
								],
							],
							__(
								'دکمه‌های ذخیره‌سازی پیش از ذخیره تنها زمانی ارائه می‌شوند که تاریخ انتشار هنوز در آینده است.',
								'music-wave-core'
							),
						],
						[
							'text',
							'label',
							__( 'برچسب دکمه', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'addedLabel',
							__( 'برچسب حالت ذخیره‌شده', 'music-wave-core' ),
							'',
						],
						[
							'select',
							'style',
							__( 'سبک دکمه', 'music-wave-core' ),
							[
								[ 'solid', __( 'جامد', 'music-wave-core' ) ],
								[
									'outline',
									__( 'طرح کلی', 'music-wave-core' ),
								],
								[
									'ghost',
									__( 'بی‌زمینه', 'music-wave-core' ),
								],
							],
						],
					],
					help: __(
						'از اصطلاح هنرمند ID برای تبدیل این دکمه به یک دکمه هنرمند برای یک هنرمند ثابت استفاده کنید.',
						'music-wave-core'
					),
				},
				{
					title: __( 'هدف هنرمند', 'music-wave-core' ),
					controls: [
						[
							'intText',
							'termId',
							__( 'اصطلاح هنرمند ID', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'برای هدف‌گرفتن انتشار انتخاب‌شده، مقدار ۰ را وارد کنید. برای دنبال‌کردن آن هنرمند، به‌جای آن شناسهٔ عددی طبقه‌بندی هنرمند را وارد کنید.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/artist-profile': {
			artistProfile: true,
		},
		'music-wave/artists-shelf': {
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[ 'text', 'eyebrow', __( 'ابرو', 'music-wave-core' ) ],
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
							__(
								'برای استفاده از عنوان پیش‌فرض ترجمه‌شده خالی بگذارید.',
								'music-wave-core'
							),
						],
						[
							'text',
							'description',
							__( 'متن مقدمه', 'music-wave-core' ),
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'sectionUrl',
							__( 'مشاهدهٔ همهٔ نشانی پیوند', 'music-wave-core' ),
							'',
							__(
								'معمولاً صفحه کاتالوگ یا صفحه فهرست هنرمندان.',
								'music-wave-core'
							),
						],
						[
							'text',
							'sectionLinkLabel',
							__(
								'برچسب پیوند «مشاهدهٔ همه»',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'منبع', 'music-wave-core' ),
					controls: [
						[
							'select',
							'source',
							__(
								'کدام هنرمندان ظاهر می‌شوند',
								'music-wave-core'
							),
							[
								[
									'all',
									__(
										'همه هنرمندان کاتالوگ',
										'music-wave-core'
									),
								],
								[
									'genre',
									__( 'هنرمندان یک ژانر', 'music-wave-core' ),
								],
								[
									'mood',
									__(
										'هنرمندان یک حال‌وهوا',
										'music-wave-core'
									),
								],
								[
									'manual',
									__(
										'فهرست دست‌چین‌شده',
										'music-wave-core'
									),
								],
							],
						],
						[
							'text',
							'termSlug',
							__( 'ژانر یا حال‌وهوا', 'music-wave-core' ),
							'',
							__(
								'برای منبع ژانر و حال‌وهوا استفاده می‌شود. مثال: پاپ.',
								'music-wave-core'
							),
						],
						[
							'text',
							'artistIds',
							__( 'شناسه‌های اصطلاح هنرمند', 'music-wave-core' ),
							'',
							__(
								'استفاده شده توسط منبع منتخب. شناسه‌های عددی جداشده با ویرگول، ترتیب دقیق شما را حفظ می‌کنند.',
								'music-wave-core'
							),
						],
						[
							'select',
							'orderBy',
							__( 'به ترتیب', 'music-wave-core' ),
							[
								[
									'count',
									__(
										'بیشترین انتشار اول',
										'music-wave-core'
									),
								],
								[ 'name', __( 'نام A–Z', 'music-wave-core' ) ],
								[ 'rand', __( 'تصادفی', 'music-wave-core' ) ],
							],
							__(
								'شمارش فقط انتشارهای منتشرشده را پوشش می‌دهد.',
								'music-wave-core'
							),
						],
						[
							'range',
							'itemsToShow',
							__( 'حداکثر هنرمندان', 'music-wave-core' ),
							1,
							24,
							undefined,
							8,
						],
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
						],
					],
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[
									'scroll',
									__( 'قفسه افقی', 'music-wave-core' ),
								],
								[
									'list',
									__( 'فهرست جمع‌وجور', 'music-wave-core' ),
								],
							],
							__(
								'از کروم اشتراک‌گذاری قفسه استفادهٔ مجدد می‌کند، بنابراین با قفسه‌های دیگر مطابقت دارد.',
								'music-wave-core'
							),
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							4,
						],
						[
							'select',
							'imageShape',
							__( 'شکل آواتار', 'music-wave-core' ),
							[
								[ 'circle', __( 'دایره', 'music-wave-core' ) ],
								[
									'rounded',
									__( 'گوشه‌های گرد', 'music-wave-core' ),
								],
								[ 'square', __( 'مربع', 'music-wave-core' ) ],
							],
						],
						[
							'select',
							'imageSize',
							__( 'اندازه تصویر', 'music-wave-core' ),
							[
								[
									'thumbnail',
									__( 'تصویر کوچک', 'music-wave-core' ),
								],
								[ 'medium', __( 'متوسط', 'music-wave-core' ) ],
								[ 'large', __( 'بزرگ', 'music-wave-core' ) ],
								[ 'full', __( 'کامل', 'music-wave-core' ) ],
							],
						],
						[
							'toggle',
							'showImage',
							__( 'نمایش آواتار', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showName',
							__( 'نمایش نام هنرمند', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showReleaseCount',
							__( 'نمایش تعداد انتشارها', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showBio',
							__( 'نمایش گزیده بیوگرافی', 'music-wave-core' ),
							false,
						],
						[
							'range',
							'bioLength',
							__( 'طول بیوگرافی (کلمات)', 'music-wave-core' ),
							5,
							80,
							undefined,
							20,
						],
						[
							'toggle',
							'showFollowButton',
							__( 'نمایش دکمه دنبال هنرمند', 'music-wave-core' ),
							true,
							__(
								'از دکمه دنبال‌کردن کتابخانه شخصی دوباره استفاده می‌کند، بنابراین دنبال کنندگان در همه جا همگام می‌مانند.',
								'music-wave-core'
							),
						],
					],
				},
			],
		},
		'music-wave/taxonomy-shelf': {
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[ 'text', 'eyebrow', __( 'ابرو', 'music-wave-core' ) ],
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
							__(
								'برای استفاده از عنوان پیش‌فرض ترجمه‌شده خالی بگذارید.',
								'music-wave-core'
							),
						],
						[
							'text',
							'description',
							__( 'متن مقدمه', 'music-wave-core' ),
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'sectionUrl',
							__( 'مشاهدهٔ همهٔ نشانی پیوند', 'music-wave-core' ),
						],
						[
							'text',
							'sectionLinkLabel',
							__(
								'برچسب پیوند «مشاهدهٔ همه»',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'منبع', 'music-wave-core' ),
					controls: [
						[
							'select',
							'taxonomy',
							__(
								'کدام طبقه‌بندی را مرور کنیم؟',
								'music-wave-core'
							),
							[
								[
									'mw_genre',
									__( 'سبک‌ها', 'music-wave-core' ),
								],
								[
									'mw_mood',
									__( 'حال‌وهواها', 'music-wave-core' ),
								],
								[
									'mw_label',
									__( 'برچسب‌ها', 'music-wave-core' ),
								],
							],
						],
						[
							'select',
							'source',
							__( 'کدام عبارت ظاهر می‌شود', 'music-wave-core' ),
							[
								[
									'all',
									__(
										'همه اصطلاحات در حال استفاده',
										'music-wave-core'
									),
								],
								[
									'manual',
									__(
										'فهرست دست‌چین‌شده',
										'music-wave-core'
									),
								],
							],
						],
						[
							'text',
							'termIds',
							__( 'اصطلاح شناسه‌ها', 'music-wave-core' ),
							'',
							__(
								'استفاده شده توسط منبع منتخب. شناسه‌های عددی جداشده با ویرگول، ترتیب دقیق شما را حفظ می‌کنند.',
								'music-wave-core'
							),
						],
						[
							'select',
							'orderBy',
							__( 'به ترتیب', 'music-wave-core' ),
							[
								[
									'count',
									__(
										'بیشترین انتشار اول',
										'music-wave-core'
									),
								],
								[ 'name', __( 'نام A–Z', 'music-wave-core' ) ],
								[ 'rand', __( 'تصادفی', 'music-wave-core' ) ],
							],
						],
						[
							'range',
							'itemsToShow',
							__( 'حداکثر کاشی', 'music-wave-core' ),
							1,
							24,
							undefined,
							8,
						],
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
						],
					],
				},
				{
					title: __( 'کاشی', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[
									'scroll',
									__( 'ریل افقی', 'music-wave-core' ),
								],
								[
									'list',
									__( 'لیست ردیف‌ها', 'music-wave-core' ),
								],
							],
							__(
								'از کروم اشتراک‌گذاری قفسه استفادهٔ مجدد می‌کند، بنابراین با قفسه‌های دیگر مطابقت دارد.',
								'music-wave-core'
							),
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							4,
						],
						[
							'select',
							'cardStyle',
							__( 'سبک کاشی', 'music-wave-core' ),
							[
								[
									'colorful',
									__(
										'رنگارنگ (رنگ‌های انتخاب‌شده)',
										'music-wave-core'
									),
								],
								[
									'plain',
									__( 'ساده (سطح آرام)', 'music-wave-core' ),
								],
							],
						],
						[
							'toggle',
							'showCount',
							__( 'نمایش تعداد انتشارها', 'music-wave-core' ),
							true,
						],
					],
				},
			],
		},
		'music-wave/term-hero': {
			groups: [
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[
									'banner',
									__(
										'بنر (جلد تمام عرض)',
										'music-wave-core'
									),
								],
								[
									'compact',
									__(
										'فشرده (ردیف کوچک)',
										'music-wave-core'
									),
								],
							],
						],
						[
							'select',
							'size',
							__( 'ارتفاع بنر', 'music-wave-core' ),
							[
								[ 'short', __( 'کوتاه', 'music-wave-core' ) ],
								[ 'medium', __( 'متوسط', 'music-wave-core' ) ],
								[ 'tall', __( 'بلند', 'music-wave-core' ) ],
							],
						],
						[
							'select',
							'imageShape',
							__( 'شکل تصویر فشرده', 'music-wave-core' ),
							[
								[
									'rounded',
									__( 'گوشه‌های گرد', 'music-wave-core' ),
								],
								[ 'circle', __( 'دایره', 'music-wave-core' ) ],
								[ 'square', __( 'مربع', 'music-wave-core' ) ],
							],
						],
					],
				},
				{
					title: __( 'محتوا', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showImage',
							__( 'نمایش تصویر جلد', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showEyebrow',
							__(
								'نمایش برچسب بالای طبقه‌بندی',
								'music-wave-core'
							),
							true,
						],
						[
							'toggle',
							'showName',
							__( 'نمایش نام', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showCount',
							__( 'نمایش تعداد انتشارها', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showDescription',
							__( 'نمایش توضیحات', 'music-wave-core' ),
							true,
						],
						[
							'range',
							'descriptionLength',
							__( 'طول توضیحات (کلمات)', 'music-wave-core' ),
							0,
							120,
							__(
								'۰ برای نمایش توضیحات کامل است؛ هر مقدار دیگری گزیده‌ای کوتاه‌شده را نشان می‌دهد.',
								'music-wave-core'
							),
							40,
						],
						[
							'toggle',
							'showFollowButton',
							__( 'نمایش دکمه دنبال هنرمند', 'music-wave-core' ),
							true,
							__(
								'دکمه فقط برای هنرمندان نمایش داده می‌شود؛ برای ژانرها، حال‌وهواها و برچسب‌ها پنهان می‌ماند.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'یک اصطلاح را سنجاق کنید', 'music-wave-core' ),
					controls: [
						[
							'select',
							'taxonomy',
							__( 'طبقه‌بندی', 'music-wave-core' ),
							[
								[
									'',
									__(
										'خودکار (بایگانی فعلی)',
										'music-wave-core'
									),
								],
								[
									'mw_artist',
									__( 'هنرمند', 'music-wave-core' ),
								],
								[ 'mw_genre', __( 'سبک', 'music-wave-core' ) ],
								[
									'mw_mood',
									__( 'حال‌وهوا', 'music-wave-core' ),
								],
								[
									'mw_label',
									__( 'برچسب', 'music-wave-core' ),
								],
							],
						],
						[
							'intText',
							'termId',
							__( 'اصطلاح ID', 'music-wave-core' ),
						],
					],
					help: __(
						'عبارت ID را روی 0 بگذارید تا آرشیو مشاهده‌شده به‌طور خودکار نمایش داده شود.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/playlists': {
			groups: [
				{
					title: __( 'عنوان', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
					],
					help: __(
						'شنوندگان فهرست‌های پخش را در اینجا مدیریت می‌کنند. هر کنترل بدون JavaScript کار می‌کند.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/playback-queue': {
			groups: [
				{
					title: __( 'نمایش', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showArtwork',
							__( 'نمایش آثار هنری', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showPosition',
							__( 'نمایش شماره موقعیت', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtist',
							__( 'نمایش نام هنرمندان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showControls',
							__(
								'نمایش کنترل‌های کلیک و تکرار',
								'music-wave-core'
							),
							true,
						],
						[
							'toggle',
							'showClear',
							__( 'نمایش دکمه صف پاک', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
						],
					],
					help: __(
						'هر کنترل یک پست فرم ساده است، بنابراین صف با JavaScript غیرفعال کار می‌کند.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/add-to-playlist': {
			releaseId: true,
			groups: [
				{
					title: __( 'کنترل', 'music-wave-core' ),
					controls: [
						[
							'text',
							'label',
							__( 'برچسب فیلد', 'music-wave-core' ),
						],
					],
				},
			],
		},
		'music-wave/share-button': {
			releaseId: true,
			textFields: [ [ 'label', __( 'برچسب دکمه', 'music-wave-core' ) ] ],
			groups: [
				{
					title: __( 'نمایش', 'music-wave-core' ),
					controls: [
						[
							'text',
							'label',
							__( 'برچسب دکمه', 'music-wave-core' ),
						],
					],
					help: __(
						'برای استفاده از برچسب پیش‌فرض ترجمه‌شده خالی بگذارید.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/shuffle-button': {
			releaseId: true,
			textFields: [ [ 'label', __( 'برچسب دکمه', 'music-wave-core' ) ] ],
			groups: [
				{
					title: __( 'نمایش', 'music-wave-core' ),
					controls: [
						[
							'text',
							'label',
							__( 'برچسب دکمه', 'music-wave-core' ),
						],
					],
					help: __(
						'پخش تصادفی صف پیش‌نمایش را به‌صورت تصادفی مرتب می‌کند.',
						'music-wave-core'
					),
				},
			],
		},
		'music-wave/add-to-queue': {
			releaseId: true,
			groups: [
				{
					title: __( 'کنترل', 'music-wave-core' ),
					controls: [
						[
							'select',
							'position',
							__( 'موقعیت فرود', 'music-wave-core' ),
							[
								[
									'next',
									__(
										'پخش بعدی (بعد از فعلی)',
										'music-wave-core'
									),
								],
								[
									'end',
									__(
										'به انتهای صف اضافه کنید',
										'music-wave-core'
									),
								],
							],
							__(
								'پس از قرار گرفتن در صف، دکمه به یک نشان درون صف تبدیل می‌شود.',
								'music-wave-core'
							),
						],
						[
							'text',
							'label',
							__( 'برچسب دکمه', 'music-wave-core' ),
							'',
							__(
								'برای استفاده از برچسب پخش بعدی یا افزودن به صف، خالی بگذارید.',
								'music-wave-core'
							),
						],
					],
				},
			],
		},
		'music-wave/public-playlists': {
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[
							'text',
							'eyebrow',
							__( 'ابرو', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'intro',
							__( 'متن مقدمه', 'music-wave-core' ),
							'',
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showSearch',
							__( 'نمایش جست‌وجو', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showCount',
							__( 'نمایش کل فهرست پخش', 'music-wave-core' ),
							true,
						],
						[
							'text',
							'searchPlaceholder',
							__( 'متن جایگزین جست‌وجو', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
							'',
						],
						[
							'text',
							'sectionUrl',
							__( 'مشاهدهٔ همهٔ نشانی پیوند', 'music-wave-core' ),
							'',
							__(
								'پیوند سربرگ قفسه انتشار را منعکس می‌کند. خالی بگذارید تا پنهان شود.',
								'music-wave-core'
							),
						],
						[
							'text',
							'sectionLinkLabel',
							__(
								'برچسب پیوند «مشاهدهٔ همه»',
								'music-wave-core'
							),
							'',
							__(
								'برای به ارث بردن برچسب پیش‌فرض MusicWave ترجمه‌شده، خالی بگذارید.',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[
									'scroll',
									__( 'قفسه افقی', 'music-wave-core' ),
								],
								[ 'list', __( 'فهرست', 'music-wave-core' ) ],
							],
							__(
								'قفسه افقی با رفتار قفسه انتشار MusicWave مطابقت دارد.',
								'music-wave-core'
							),
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							3,
						],
						[
							'select',
							'imageShape',
							__( 'شکل اثر هنری', 'music-wave-core' ),
							[
								[ 'square', __( 'مربع', 'music-wave-core' ) ],
								[
									'landscape',
									__( 'منظره', 'music-wave-core' ),
								],
								[
									'portrait',
									__( 'پرتره', 'music-wave-core' ),
								],
								[ 'circle', __( 'دایره', 'music-wave-core' ) ],
							],
						],
						[
							'range',
							'itemsToShow',
							__( 'موارد در هر صفحه', 'music-wave-core' ),
							4,
							24,
							undefined,
							12,
						],
						[
							'select',
							'orderby',
							__( 'به ترتیب', 'music-wave-core' ),
							[
								[
									'updated_at',
									__(
										'به‌تازگی به‌روزشده',
										'music-wave-core'
									),
								],
								[
									'created_at',
									__( 'جدیدترین اول', 'music-wave-core' ),
								],
								[
									'title',
									__( 'عنوان A–Z', 'music-wave-core' ),
								],
							],
						],
						[
							'toggle',
							'showPagination',
							__( 'نمایش صفحه‌بندی', 'music-wave-core' ),
							true,
						],
					],
				},
				{
					title: __( 'مشخصات کارت', 'music-wave-core' ),
					controls: [
						[
							'toggle',
							'showArt',
							__( 'نمایش شبکهٔ تصاویر جلد', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showPlayButton',
							__( 'نمایش دکمه پخش', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showToggle',
							__(
								'نمایش تغییر فهرست قطعه‌ها',
								'music-wave-core'
							),
							true,
						],
						[
							'toggle',
							'showAuthor',
							__( 'نمایش نام متصدی', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showUpdated',
							__( 'نمایش تاریخ به‌روزرسانی', 'music-wave-core' ),
							true,
						],
					],
				},
			],
		},
		'music-wave/continue-listening': {
			groups: [
				{
					title: __( 'سربرگ', 'music-wave-core' ),
					controls: [
						[
							'text',
							'heading',
							__( 'عنوان بخش', 'music-wave-core' ),
							'',
							__(
								'برای استفاده از پیش‌فرض هوشمند، خالی بگذارید: به گوش‌دادن ادامه دهید یا اخیراً پخش‌شده است.',
								'music-wave-core'
							),
						],
						[
							'text',
							'intro',
							__( 'متن مقدمه', 'music-wave-core' ),
						],
						[
							'toggle',
							'showHeading',
							__( 'نمایش عنوان', 'music-wave-core' ),
							true,
						],
						[
							'select',
							'source',
							__(
								'کدام فعالیت ریل را تغذیه می‌کند',
								'music-wave-core'
							),
							[
								[
									'continue',
									__(
										'انتشارهای در حال پیشرفت',
										'music-wave-core'
									),
								],
								[
									'played',
									__(
										'انتشارهای اخیراً پخش‌شده',
										'music-wave-core'
									),
								],
							],
						],
						[
							'text',
							'sectionUrl',
							__( 'مشاهدهٔ همهٔ نشانی پیوند', 'music-wave-core' ),
						],
						[
							'text',
							'sectionLinkLabel',
							__(
								'برچسب پیوند «مشاهدهٔ همه»',
								'music-wave-core'
							),
						],
					],
				},
				{
					title: __( 'چیدمان', 'music-wave-core' ),
					controls: [
						[
							'select',
							'layout',
							__( 'چیدمان', 'music-wave-core' ),
							[
								[
									'scroll',
									__( 'قفسه افقی', 'music-wave-core' ),
								],
								[ 'grid', __( 'شبکه', 'music-wave-core' ) ],
								[ 'list', __( 'فهرست', 'music-wave-core' ) ],
							],
							__(
								'از کروم اشتراک‌گذاری قفسه استفادهٔ مجدد می‌کند، بنابراین با قفسه‌های دیگر مطابقت دارد.',
								'music-wave-core'
							),
						],
						[
							'range',
							'columns',
							__( 'ستون‌های شبکه', 'music-wave-core' ),
							2,
							6,
							undefined,
							4,
						],
						[
							'range',
							'itemsToShow',
							__( 'حداکثر موارد', 'music-wave-core' ),
							2,
							24,
							undefined,
							8,
						],
						[
							'select',
							'imageShape',
							__( 'شکل اثر هنری', 'music-wave-core' ),
							[
								[ 'square', __( 'مربع', 'music-wave-core' ) ],
								[ 'circle', __( 'دایره', 'music-wave-core' ) ],
								[
									'landscape',
									__( 'منظره', 'music-wave-core' ),
								],
								[
									'portrait',
									__( 'پرتره', 'music-wave-core' ),
								],
							],
						],
						[
							'toggle',
							'showArtwork',
							__( 'نمایش آثار هنری', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showArtist',
							__( 'نمایش نام هنرمندان', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showWhen',
							__( 'نمایش زمان پخش قبلی', 'music-wave-core' ),
							true,
						],
						[
							'toggle',
							'showPreview',
							__( 'نمایش همپوشانی پخش', 'music-wave-core' ),
							true,
						],
					],
				},
				{
					title: __( 'پیام‌ها', 'music-wave-core' ),
					controls: [
						[
							'text',
							'emptyMessage',
							__( 'پیام حالت خالی', 'music-wave-core' ),
						],
						[
							'text',
							'guestMessage',
							__( 'درخواست مهمان', 'music-wave-core' ),
						],
						[
							'text',
							'consentMessage',
							__( 'متن پانل رضایت', 'music-wave-core' ),
						],
						[
							'text',
							'consentButtonLabel',
							__( 'برچسب دکمه رضایت', 'music-wave-core' ),
						],
					],
					help: __(
						'تاریخچه گوش‌دادن چیزی را ضبط نمی‌کند تا زمانی که هر شنونده ای را انتخاب کند. پس گرفتن رضایت، تاریخچه ذخیره‌شده را فوراً پاک می‌کند.',
						'music-wave-core'
					),
				},
			],
		},
	};

	var emptyStateCopy = {
		'music-wave/release-meta': __(
			'شماره کاتالوگ، تاریخ انتشار، مدت زمان، BPM، کلید، هنرمند یا ژانر را به انتشار انتخابی اضافه کنید.',
			'music-wave-core'
		),
		'music-wave/access-panel': __(
			'حالت دسترسی عمومی عمداًً هیچ پنلی ندارد. برای پیش‌نمایش این بلوک، انتشار حفاظت‌شده، خرید یا عضویت را انتخاب کنید.',
			'music-wave-core'
		),
		'music-wave/release-credits': __(
			'برای نمایش بخش اعتبار، حداقل یک اعتبار در جزئیات انتشار اضافه کنید.',
			'music-wave-core'
		),
		'music-wave/collection-list': __(
			'قطعه‌ها یا قسمت‌ها را به مجموعه انتخابی اضافه کنید تا لیست قطعه‌های مرتب‌شده آن نمایش داده شود.',
			'music-wave-core'
		),
		'music-wave/catalog-filters': __(
			'فیلترهای کاتالوگ از هنرمند، ژانر، حال و هوا و شرایط انتشار در آرشیو عمومی شما ایجاد می‌شوند.',
			'music-wave-core'
		),
		'music-wave/catalog-results': __(
			'خلاصهٔ نتایج زمانی ظاهر می‌شود که کاتالوگ عمومی دارای گزینه جست‌وجو، فیلتر یا مرتب‌سازی فعال باشد.',
			'music-wave-core'
		),
		'music-wave/preview-player': __(
			'برای نمایش پخش‌کننده، یک نشانی HTTPS امنِ پیش‌نمایش به انتشار انتخاب‌شده اضافه کنید.',
			'music-wave-core'
		),
		'music-wave/download-button': __(
			'حداقل یک فایل دانلود حفاظت‌شده اضافه کنید و این بلوک را به عنوان یک کاربر واجد شرایط واردشده به سیستم پیش‌نمایش کنید.',
			'music-wave-core'
		),
		'music-wave/related-releases': __(
			'بخش‌های مرتبط زمانی ظاهر می‌شوند که انتشار انتخابی هنرمندان، ژانرها، حال‌وهواها یا انواع انتشار را با انتشارهای دیگر به اشتراک بگذارد.',
			'music-wave-core'
		),
		'music-wave/artist-profile': __(
			'محتوای نمایهٔ هنرمند پس از اضافه‌شدن تصویر، بیوگرافی یا URL رسمی به آن هنرمند، در بایگانی هنرمند نمایش داده می‌شود.',
			'music-wave-core'
		),
		'music-wave/preview-button': __(
			'برای نمایش دکمه پخش، نشانی HTTPS امنِ پیش‌نمایش را به انتشار انتخاب‌شده اضافه کنید.',
			'music-wave-core'
		),
		'music-wave/music-library': __(
			'کتابخانه شخصی هر آهنگ، آلبوم، پادکست و هنرمندی را فهرست می‌کند که بازدیدکننده واردشده با دکمه افزودن به کتابخانه ذخیره می‌کند.',
			'music-wave-core'
		),
		'music-wave/library-button': __(
			'این دکمه انتشار انتخاب‌شده را در کتابخانه شخصی یک بازدیدکننده ذخیره می‌کند، یا زمانی که یک اصطلاح هنرمند ID تنظیم می‌شود، هنرمند را دنبال می‌کند.',
			'music-wave-core'
		),
		'music-wave/playlists': __(
			'شنوندگانی که به سیستم واردشده‌اند، فهرست‌های پخش خود را با کنترل‌های ایجاد، تغییر نام، اشتراک‌گذاری، مرتب‌سازی مجدد و حذف در اینجا می‌بینند.',
			'music-wave-core'
		),
		'music-wave/public-playlists': __(
			'فهرست‌های پخش عمومی جامعه را جست‌وجو کنید، صفحه‌بندی کنید و با یک کلیک پخش کنید.',
			'music-wave-core'
		),
		'music-wave/membership-panel': __(
			'سطوح عضویت فعالِ دارای تاریخ انقضا و محصولات طرح VIP که برای بازدیدکنندگان واردشده پیکربندی‌شده‌اند، اینجا نمایش داده می‌شوند. به MusicWave VIP نیاز دارد.',
			'music-wave-core'
		),
		'music-wave/add-to-playlist': __(
			'شنوندگانی که به سیستم واردشده‌اند می‌توانند انتشار انتخاب‌شده را به یکی از فهرست‌های پخش خود اضافه کنند.',
			'music-wave-core'
		),
		'music-wave/continue-listening': __(
			'هر شنونده پس از انتخاب و پخش چیزی، این ریل را می‌بیند. مهمان‌ها پیام ورود به سیستم را می‌بینند و شنوندگانی که انصراف داده‌اند، پنل رضایت را با یک کلیک مشاهده می‌کنند.',
			'music-wave-core'
		),
		'music-wave/artists-shelf': __(
			'هنرمندان با انتشارهای منتشرشده به‌طور خودکار ظاهر می‌شوند. برای بهترین نمایش، تصاویر هنرمند را در ویرایشگر انتشار اختصاص دهید.',
			'music-wave-core'
		),
		'music-wave/taxonomy-shelf': __(
			'ژانرها، حال‌وهواها یا برچسب‌های اختصاص‌داده‌شده به انتشارها به‌طور خودکار در اینجا به‌عنوان کاشی‌های مرور قابل کلیک ظاهر می‌شوند.',
			'music-wave-core'
		),
		'music-wave/term-hero': __(
			'این سربرگ در بایگانی‌های هنرمند، ژانر، حال‌وهوا و برچسب به‌طور خودکار نمایش داده می‌شود. برای استفاده در هر جای دیگر، یک شناسهٔ اصطلاح را سنجاق کنید.',
			'music-wave-core'
		),
		'music-wave/playback-queue': __(
			'شنوندگانی که وارد سیستم شده‌اند، صف پخش پایدار خود را اینجا می‌بینند. مهمانان پیام ورود به سیستم را مشاهده می‌کنند.',
			'music-wave-core'
		),
		'music-wave/share-button': __(
			'دکمه اشتراک‌گذاری انتشار را در نوار اقدام انتشار نمایش می‌دهد.',
			'music-wave-core'
		),
		'music-wave/shuffle-button': __(
			'دکمه پخش تصادفی مجموعه یا انتشار فعلی را نمایش می‌دهد.',
			'music-wave-core'
		),
		'music-wave/add-to-queue': __(
			'این بلوک را در یک انتشار پیش‌نمایش کنید یا با انتخابگر انتشار، یک مورد را سنجاق کنید. شنوندگان واردشده آن را فوراً در صف قرار می‌دهند.',
			'music-wave-core'
		),
	};

	function useEditorReleaseId( props ) {
		var editorId = useSelect( function ( select ) {
			var editor = select ? select( 'core/editor' ) : null;
			if (
				! editor ||
				! editor.getCurrentPostType ||
				! editor.getCurrentPostId
			) {
				return 0;
			}
			return 'mw_release' === editor.getCurrentPostType()
				? editor.getCurrentPostId()
				: 0;
		}, [] );

		if (
			props.context &&
			'mw_release' === props.context.postType &&
			parseInt( props.context.postId, 10 ) > 0
		) {
			return parseInt( props.context.postId, 10 );
		}

		return editorId;
	}

	function useReleaseOptions( releaseId ) {
		return useSelect(
			function ( select ) {
				// A negative sentinel skips the lookup for blocks that do
				// not expose a release picker, keeping the hook call itself
				// unconditional on every render.
				if ( releaseId < 0 ) {
					return [];
				}
				var core = select ? select( 'core' ) : null;
				if ( ! core || ! core.getEntityRecords ) {
					return [];
				}

				var query = {
					per_page: 50,
					orderby: 'date',
					order: 'desc',
					status: [ 'publish', 'draft', 'pending', 'private' ],
				};
				if ( releaseId > 0 ) {
					query.include = [ releaseId ];
				}

				var records =
					core.getEntityRecords( 'postType', 'mw_release', query ) ||
					[];
				return records.map( function ( record ) {
					var title =
						record && record.title && record.title.rendered
							? record.title.rendered
							: '';
					return {
						label:
							title !== ''
								? title.replace( /<[^>]*>/g, '' )
								: '#' + record.id,
						value: record.id,
					};
				} );
			},
			[ releaseId ]
		);
	}

	function releaseSelect( props, options, contextualId ) {
		var choices = [
			{
				label:
					contextualId > 0
						? __( 'انتشار فعلی', 'music-wave-core' )
						: __( 'خودکار / انتشار فعلی', 'music-wave-core' ),
				value: 0,
			},
		];

		options.forEach( function ( option ) {
			choices.push( option );
		} );

		return createElement( components.SelectControl, {
			label: __( 'پیش‌نمایش انتشار', 'music-wave-core' ),
			value: props.attributes.releaseId || 0,
			options: choices,
			onChange( value ) {
				props.setAttributes( {
					releaseId: parseInt( value, 10 ) || 0,
				} );
			},
			help: __(
				'این خودکار را در قالب‌های انتشار و حلقه‌های پرس‌وجو بگذارید. تنها زمانی یک انتشار ثابت را انتخاب کنید که بلوک همیشه آن انتشار را نشان دهد.',
				'music-wave-core'
			),
		} );
	}

	function artistProfileControls( props ) {
		return [
			createElement( components.TextControl, {
				key: 'termId',
				label: __( 'اصطلاح هنرمند ID', 'music-wave-core' ),
				help: __(
					'یک شناسهٔ عددیِ طبقه‌بندی هنرمند وارد کنید تا این بلوک در هر صفحه‌ای به آن هنرمند سنجاق شود. برای استفاده از بایگانی هنرمندی که اکنون مشاهده می‌کنید، ۰ را وارد کنید.',
					'music-wave-core'
				),
				value: String( props.attributes.termId || '' ),
				onChange( value ) {
					var id = parseInt( value, 10 );
					props.setAttributes( { termId: isNaN( id ) ? 0 : id } );
				},
			} ),
			createElement( components.SelectControl, {
				key: 'layout',
				label: __( 'چیدمان', 'music-wave-core' ),
				value: props.attributes.layout || 'card',
				options: [
					{ label: __( 'کارت', 'music-wave-core' ), value: 'card' },
					{
						label: __( 'فهرست (کنار به پهلو)', 'music-wave-core' ),
						value: 'list',
					},
					{
						label: __( 'لغزنده / برجسته', 'music-wave-core' ),
						value: 'slider',
					},
				],
				onChange( value ) {
					props.setAttributes( { layout: value } );
				},
			} ),
			createElement( components.SelectControl, {
				key: 'imageSize',
				label: __( 'اندازه تصویر', 'music-wave-core' ),
				value: props.attributes.imageSize || 'medium',
				options: [
					{
						label: __( 'تصویر کوچک', 'music-wave-core' ),
						value: 'thumbnail',
					},
					{
						label: __( 'متوسط', 'music-wave-core' ),
						value: 'medium',
					},
					{ label: __( 'بزرگ', 'music-wave-core' ), value: 'large' },
					{ label: __( 'کامل', 'music-wave-core' ), value: 'full' },
				],
				onChange( value ) {
					props.setAttributes( { imageSize: value } );
				},
			} ),
			createElement( components.SelectControl, {
				key: 'imageShape',
				label: __( 'شکل تصویر', 'music-wave-core' ),
				value: props.attributes.imageShape || 'rounded',
				options: [
					{
						label: __( 'گوشه‌های گرد', 'music-wave-core' ),
						value: 'rounded',
					},
					{
						label: __( 'مربع', 'music-wave-core' ),
						value: 'square',
					},
					{
						label: __( 'دایره', 'music-wave-core' ),
						value: 'circle',
					},
				],
				onChange( value ) {
					props.setAttributes( { imageShape: value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showImage',
				label: __( 'نمایش تصویر هنرمند', 'music-wave-core' ),
				checked: false !== props.attributes.showImage,
				onChange( value ) {
					props.setAttributes( { showImage: !! value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showTitle',
				label: __( 'نمایش نام هنرمند', 'music-wave-core' ),
				checked: false !== props.attributes.showTitle,
				onChange( value ) {
					props.setAttributes( { showTitle: !! value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showBio',
				label: __( 'نمایش بیوگرافی', 'music-wave-core' ),
				checked: false !== props.attributes.showBio,
				onChange( value ) {
					props.setAttributes( { showBio: !! value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showLink',
				label: __( 'نمایش پیوند خارجی', 'music-wave-core' ),
				checked: false !== props.attributes.showLink,
				onChange( value ) {
					props.setAttributes( { showLink: !! value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showLibraryButton',
				label: __( 'نمایش دکمه دنبال هنرمند', 'music-wave-core' ),
				help: __(
					'به بازدیدکنندگان اجازه می‌دهد این هنرمند را در کتابخانه موسیقی شخصی خود ذخیره کنند.',
					'music-wave-core'
				),
				checked: false !== props.attributes.showLibraryButton,
				onChange( value ) {
					props.setAttributes( { showLibraryButton: !! value } );
				},
			} ),
			createElement( components.ToggleControl, {
				key: 'showReleaseCount',
				label: __( 'نمایش تعداد انتشارها', 'music-wave-core' ),
				help: __(
					'نشانی با تعداد انتشارات منتشرشده اختصاص‌داده‌شده به این هنرمند نشان می‌دهد.',
					'music-wave-core'
				),
				checked: !! props.attributes.showReleaseCount,
				onChange( value ) {
					props.setAttributes( { showReleaseCount: !! value } );
				},
			} ),
			createElement( components.RangeControl, {
				key: 'bioLength',
				label: __( 'طول بیوگرافی (کلمات)', 'music-wave-core' ),
				help: __(
					'۰ برای نگه‌داشتن بیوگرافی کامل است؛ هر مقدار دیگری گزیده‌ای کوتاه‌شده را نشان می‌دهد.',
					'music-wave-core'
				),
				value: props.attributes.bioLength || 0,
				min: 0,
				max: 200,
				onChange( value ) {
					props.setAttributes( {
						bioLength: parseInt( value, 10 ) || 0,
					} );
				},
			} ),
			createElement( components.TextControl, {
				key: 'ctaLabel',
				label: __( 'برچسب دکمه پیوند', 'music-wave-core' ),
				help: __(
					'برای استفاده از برچسب پیش‌فرض خالی بگذارید.',
					'music-wave-core'
				),
				value: props.attributes.ctaLabel || '',
				onChange( value ) {
					props.setAttributes( { ctaLabel: value } );
				},
			} ),
			createElement(
				'div',
				{
					key: 'accentColor',
					className: 'mw-block-editor-color-field',
				},
				createElement(
					'p',
					{ className: 'components-base-control__label' },
					__( 'رنگ تأکیدی', 'music-wave-core' )
				),
				createElement( components.ColorPalette, {
					value: props.attributes.accentColor || undefined,
					clearable: true,
					onChange( color ) {
						props.setAttributes( { accentColor: color || '' } );
					},
				} ),
				createElement(
					'p',
					{ className: 'components-base-control__help' },
					__(
						'لهجه موضوع را برای نام و پیوند لغو می‌کند. برای استفاده از پیش‌فرض آن را پاک کنید.',
						'music-wave-core'
					)
				)
			),
		];
	}

	/**
	 * Build one inspector control from a declarative group descriptor.
	 *
	 * Descriptor formats:
	 * ['text', attr, label, default?, help?]             TextControl
	 * ['intText', attr, label]                           TextControl storing an integer
	 * ['toggle', attr, label, default, help?]            ToggleControl
	 * ['select', attr, label, [[value, label]], help?]   SelectControl
	 * ['range', attr, label, min, max, help?, fallback?]   RangeControl
	 */
	function buildGroupControl( descriptor, props ) {
		var type = descriptor[ 0 ];
		var attr = descriptor[ 1 ];
		var label = descriptor[ 2 ];

		if ( 'text' === type ) {
			return createElement( components.TextControl, {
				key: attr,
				label,
				value: props.attributes[ attr ] || '',
				help: descriptor[ 4 ] || undefined,
				onChange( value ) {
					var update = {};
					update[ attr ] = value;
					props.setAttributes( update );
				},
			} );
		}

		if ( 'intText' === type ) {
			return createElement( components.TextControl, {
				key: attr,
				label,
				value: props.attributes[ attr ]
					? String( props.attributes[ attr ] )
					: '',
				onChange( value ) {
					var parsed = parseInt( value, 10 );
					var update = {};
					update[ attr ] = isNaN( parsed ) ? 0 : parsed;
					props.setAttributes( update );
				},
			} );
		}

		if ( 'toggle' === type ) {
			return createElement( components.ToggleControl, {
				key: attr,
				label,
				checked:
					props.attributes[ attr ] === undefined
						? !! descriptor[ 3 ]
						: !! props.attributes[ attr ],
				help: descriptor[ 4 ] || undefined,
				onChange( value ) {
					var update = {};
					update[ attr ] = !! value;
					props.setAttributes( update );
				},
			} );
		}

		if ( 'select' === type ) {
			return createElement( components.SelectControl, {
				key: attr,
				label,
				value: props.attributes[ attr ],
				options: ( descriptor[ 3 ] || [] ).map( function ( option ) {
					return { value: option[ 0 ], label: option[ 1 ] };
				} ),
				help: descriptor[ 4 ] || undefined,
				onChange( value ) {
					var update = {};
					update[ attr ] = value;
					props.setAttributes( update );
				},
			} );
		}

		if ( 'range' === type ) {
			return createElement( components.RangeControl, {
				key: attr,
				label,
				value:
					props.attributes[ attr ] ||
					descriptor[ 6 ] ||
					descriptor[ 3 ],
				min: descriptor[ 3 ],
				max: descriptor[ 4 ],
				help: descriptor[ 5 ] || undefined,
				onChange( value ) {
					var update = {};
					update[ attr ] = parseInt( value, 10 ) || descriptor[ 3 ];
					props.setAttributes( update );
				},
			} );
		}

		return null;
	}

	function inspectorControls( props, blockName, options, contextualId ) {
		var config = fieldConfig[ blockName ] || {};
		var controls = [];

		if ( config.artistProfile ) {
			controls = artistProfileControls( props );
		}

		if ( config.releaseId ) {
			controls.push( releaseSelect( props, options, contextualId ) );
		}

		if ( config.compact ) {
			controls.push(
				createElement( components.ToggleControl, {
					key: 'compact',
					label: __( 'نمایشگر فشرده', 'music-wave-core' ),
					checked: !! props.attributes.compact,
					onChange( value ) {
						props.setAttributes( { compact: !! value } );
					},
					help: __(
						'از چیدمان متراکم‌تر برای کارت‌ها، فهرست‌ها و نوارهای کناری استفاده کنید.',
						'music-wave-core'
					),
				} )
			);
		}

		( config.textFields || [] ).forEach( function ( field ) {
			controls.push(
				createElement( components.TextControl, {
					key: field[ 0 ],
					label: field[ 1 ],
					value: props.attributes[ field[ 0 ] ] || '',
					onChange( value ) {
						var update = {};
						update[ field[ 0 ] ] = value;
						props.setAttributes( update );
					},
					help: __(
						'برای به ارث بردن برچسب پیش‌فرض MusicWave ترجمه‌شده، خالی بگذارید.',
						'music-wave-core'
					),
				} )
			);
		} );

		( config.toggles || [] ).forEach( function ( field ) {
			controls.push(
				createElement( components.ToggleControl, {
					key: field[ 0 ],
					label: field[ 1 ],
					checked: false !== props.attributes[ field[ 0 ] ],
					onChange( value ) {
						var update = {};
						update[ field[ 0 ] ] = !! value;
						props.setAttributes( update );
					},
				} )
			);
		} );

		if ( config.range ) {
			controls.push(
				createElement( components.RangeControl, {
					key: config.range[ 0 ],
					label: config.range[ 1 ],
					value: props.attributes[ config.range[ 0 ] ] || 4,
					min: config.range[ 2 ],
					max: config.range[ 3 ],
					onChange( value ) {
						var update = {};
						update[ config.range[ 0 ] ] =
							parseInt( value, 10 ) || 4;
						props.setAttributes( update );
					},
				} )
			);
		}

		if ( ! controls.length && ! config.groups ) {
			return null;
		}

		var panels = [];
		if ( controls.length ) {
			panels.push(
				createElement(
					components.PanelBody,
					{
						title: __(
							'تنظیمات بلوک MusicWave',
							'music-wave-core'
						),
						initialOpen: true,
						key: 'music-wave-settings',
					},
					controls
				)
			);
		}

		( config.groups || [] ).forEach( function ( group, index ) {
			var groupControls = ( group.controls || [] )
				.map( function ( descriptor ) {
					return buildGroupControl( descriptor, props );
				} )
				.filter( function ( control ) {
					return null !== control;
				} );
			if ( group.help ) {
				groupControls.push(
					createElement(
						'p',
						{
							className: 'components-base-control__help',
							key: 'group-help',
						},
						group.help
					)
				);
			}
			if ( groupControls.length ) {
				panels.push(
					createElement(
						components.PanelBody,
						{
							title: group.title,
							initialOpen: false,
							key: 'music-wave-group-' + index,
						},
						groupControls
					)
				);
			}
		} );

		if ( ! panels.length ) {
			return null;
		}

		return createElement( blockEditor.InspectorControls, null, panels );
	}

	function editorEmptyState( props, block, options, contextualId ) {
		var config = fieldConfig[ block.name ] || {};
		var children = [
			createElement( 'span', {
				className: 'dashicons dashicons-album',
				'aria-hidden': 'true',
				key: 'icon',
			} ),
			createElement( 'strong', { key: 'title' }, block.title ),
			createElement(
				'p',
				{ key: 'message' },
				emptyStateCopy[ block.name ] ||
					__(
						'این بلوک آماده است و وقتی داده‌های موردنیاز MusicWave در دسترس باشند، نمایش داده می‌شود.',
						'music-wave-core'
					)
			),
		];

		if ( config.releaseId && options.length ) {
			children.push(
				createElement(
					'div',
					{
						className: 'mw-block-editor-empty__control',
						key: 'control',
					},
					releaseSelect( props, options, contextualId )
				)
			);
		}

		return createElement(
			'div',
			{
				className: 'mw-block-editor-empty',
				'data-mw-empty-block': block.name,
			},
			children
		);
	}

	function editorPreview( props, block, options, contextualId ) {
		var attrs = Object.assign( {}, props.attributes );
		var config = fieldConfig[ block.name ] || {};

		if (
			config.releaseId &&
			( ! attrs.releaseId || attrs.releaseId < 1 )
		) {
			if ( contextualId > 0 ) {
				attrs.releaseId = contextualId;
			} else if ( options.length ) {
				// Use a real release only for the editor preview. Do not persist it
				// into a contextual template or Query Loop.
				attrs.releaseId = options[ 0 ].value;
			}
		}

		return createElement(
			'div',
			{
				className:
					'mw-block-editor-shell' +
					( props.attributes && props.attributes.align
						? ' align' + props.attributes.align
						: '' ),
				'data-mw-block': block.name,
			},
			createElement( serverSideRender, {
				block: block.name,
				attributes: attrs,
				EmptyResponsePlaceholder() {
					return editorEmptyState(
						props,
						block,
						options,
						contextualId
					);
				},
				ErrorResponsePlaceholder() {
					return createElement(
						components.Notice,
						{ status: 'error', isDismissible: false },
						__(
							'MusicWave نتوانست این پیش‌نمایش را بارگذاری کند. بررسی کنید MusicWave Core فعال باشد و انتشار انتخاب‌شده هنوز وجود داشته باشد.',
							'music-wave-core'
						)
					);
				},
				LoadingResponsePlaceholder() {
					return createElement( components.Spinner );
				},
			} )
		);
	}

	dynamicBlocks.forEach( function ( block ) {
		var existing = blocks.getBlockType( block.name );
		if ( existing ) {
			// Server hydration from block.json registers a bare dynamic block.
			// Re-registration only adds the editor experience: every schema
			// field the server provided (attributes, supports, context, titles)
			// stays authoritative, so block.json remains the single source of
			// truth (PROJECT_PLAN.md Stage 4 deliverable 3).
			try {
				blocks.unregisterBlockType( block.name );
			} catch ( error ) {
				return;
			}
		}

		function ReleaseFieldsEdit( props ) {
			var config = fieldConfig[ block.name ] || {};
			var contextualId = useEditorReleaseId( props );
			var options = useReleaseOptions(
				config.releaseId ? props.attributes.releaseId || 0 : -1
			);

			return createElement(
				Fragment,
				null,
				inspectorControls( props, block.name, options, contextualId ),
				editorPreview( props, block, options, contextualId )
			);
		}

		blocks.registerBlockType( block.name, {
			apiVersion:
				existing && existing.apiVersion ? existing.apiVersion : 3,
			title: ( existing && existing.title ) || block.title,
			description:
				( existing && existing.description ) || block.description,
			category: ( existing && existing.category ) || 'music-wave',
			icon: block.icon || ( existing && existing.icon ) || 'album',
			keywords: block.keywords || [],
			attributes: Object.assign(
				{},
				block.attributes || {},
				( existing && existing.attributes ) || {}
			),
			usesContext:
				existing && existing.usesContext && existing.usesContext.length
					? existing.usesContext
					: block.usesContext || [],
			supports: Object.assign(
				{},
				block.supports || {},
				( existing && existing.supports ) || {}
			),
			styles: existing && existing.styles ? existing.styles : undefined,
			example:
				block.example ||
				( existing && existing.example ? existing.example : undefined ),
			edit: ReleaseFieldsEdit,
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
	window.musicWaveDynamicBlocks || []
);
