# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package MT::Commercial::L10N::ja;

use strict;
use warnings;
use base 'MT::Commercial::L10N::en_us';
use vars qw( %Lexicon );
use utf8;
## The following is the translation table.

%Lexicon = (

## addons/Commercial.pack/config.yaml
	'A collection of styles compatible with Professional themes.' => 'プロフェッショナルテーマと互換のあるスタイルです。',
	'About This Page' => 'About',
	'Archive Index' => 'アーカイブインデックス',
	'Archive Widgets Group' => 'アーカイブウィジェットグループ',
	'Are you sure you want to delete the selected CustomFields?' => '選択したカスタムフィールドを削除してもよろしいですか？',
	'Audio' => 'オーディオ',
	'Author Archives' => 'ユーザーアーカイブ',
	'Basename' => '出力ファイル名',
	'Blog Activity' => 'アクティビティ',
	'Blog Archives' => 'アーカイブ',
	'Blog Index' => 'ブログ・インデックス',
	'Blogs' => 'ブログ',
	'Calendar' => 'カレンダー',
	'Categories' => 'カテゴリ',
	'Category Archives' => 'カテゴリアーカイブ',
	'Child Site' => '子サイト',
	'Comment Detail' => 'コメント詳細',
	'Comment Form' => 'コメント入力フォーム',
	'Comment Listing' => 'コメント一覧',
	'Comment Preview' => 'コメントプレビュー',
	'Comment Response' => 'コメント完了',
	'Comments' => 'コメント',
	'Contact' => '連絡先',
	'Converting CustomField type...' => 'カスタムフィールドのタイプを変換しています...',
	'Create a blog as a part of structured website. This works best with Professional Website theme.' => 'プロフェッショナル ウェブサイトと連携する、ブログのテーマです。',
	'Creative Commons' => 'クリエイティブ・コモンズ',
	'Current Author Monthly Archives' => 'ユーザー月別アーカイブ',
	'Current Category Monthly Archives' => 'カテゴリ月別アーカイブ',
	'Custom Fields' => 'カスタムフィールド',
	'Date-Based Author Archives' => '日付ベースのユーザーアーカイブ',
	'Date-Based Category Archives' => '日付ベースのカテゴリアーカイブ',
	'Delete' => '削除',
	'Description' => '説明',
	'Dynamic Error' => 'ダイナミックパブリッシングエラー',
	'Embed' => '埋め込み',
	'Entry Detail' => '記事の詳細',
	'Entry Listing' => '記事一覧',
	'Entry Metadata' => '記事のメタデータ',
	'Entry Summary' => '記事の概要',
	'Entry' => '記事',
	'Feed - Recent Entries' => '最新記事のフィード',
	'Field' => 'フィールド',
	'Footer Links' => 'フッターのリンク',
	'Footer' => 'フッター',
	'Header' => 'ヘッダー',
	'Home Page Widgets Group' => 'ホームページウィジェットグループ',
	'JavaScript' => 'JavaScript',
	'Link' => 'リンク',
	'Main Index' => 'メインページ',
	'Main Sidebar' => 'メインサイドバー',
	'Manage' => '一覧',
	'Migrating CustomFields type...' => 'カスタムフィールドのタイプをアップデートしています...',
	'Monthly Archives Dropdown' => '月別アーカイブ(ドロップダウン)',
	'Monthly Archives' => '月別アーカイブ',
	'Name' => '名前',
	'Navigation' => 'ナビゲーション',
	'New' => '新規',
	'No Name' => '名前なし',
	'Not Required' => '必須ではない',
	'OpenID Accepted' => 'OpenID対応',
	'Page Detail' => 'ウェブページの詳細',
	'Page Listing' => 'ページ一覧',
	'Page' => 'ウェブページ',
	'Photo' => '写真',
	'Popup Image' => 'ポップアップ画像',
	'Powered By (Footer)' => 'Powered By (フッター)',
	'Professional Blog' => 'プロフェッショナルブログ',
	'Professional Styles' => 'プロフェッショナルスタイル',
	'Professional Website' => 'プロフェッショナル ウェブサイト',
	'Professionally designed, well-structured and easily adaptable web site. You can customize default pages, footer and top navigation easily.' => 'バナー画像、水平型のナビゲーションなど、ホームページ用途に適したデザインです。あらかじめ用意されたページをカスタマイズして、簡単にウェブサイトを作成できます。',
	'RSD' => 'RSD',
	'Recent Assets' => 'アセット',
	'Recent Comments' => '最近のコメント',
	'Recent Entries Expanded' => '最近の記事 (拡張)',
	'Recent Entries' => '最近の記事',
	'Required' => '必須',
	'Search Results for Content Data' => 'コンテンツの検索結果',
	'Search Results' => '検索結果',
	'Search' => '検索',
	'Sidebar' => 'サイドバー',
	'Sign In' => 'サインイン',
	'Site Name' => 'サイト名',
	'Site' => 'サイト',
	'Stylesheet' => 'スタイルシート',
	'Syndication' => '購読',
	'System Object' => 'システムオブジェクト',
	'Tag Cloud' => 'タグクラウド',
	'Tag' => 'タグ',
	'Tags' => 'タグ',
	'Template tag' => 'テンプレートタグ',
	'TrackBacks' => 'トラックバック',
	'Type' => '種類',
	'Unknown Object' => '不明なオブジェクト',
	'Unknown Type' => '不明な種類',
	'Updating Universal Template Set to Professional Website set...' => '汎用テンプレートセットをプロフェッショナルウェブサイトテンプレートセットにアップデートしています...',
	'Welcome to our new website!' => '新しいウェブサイトへようこそ!',
	'[_1] [_3] [_2]' => '[_1] [_3] [_2]',
	'_PWT_ABOUT_BODY' => '
<p><strong>以下の文章はサンプルです。内容を適切に書き換えてください。</strong></p>
<p>いろはにほへと ちりぬるを わかよたれそ つねならむ うゐのおくやま けふこえて あさきゆめみし ゑひもせす</p>
<p>色は匂へど 散りぬるを 我が世誰ぞ 常ならむ 有為の奥山 今日越えて 浅き夢見じ 酔ひもせず</p>
',
	'_PWT_CONTACT_BODY' => '
<p><strong>以下の文章はサンプルです。内容を適切に書き換えてください。</strong></p>
<p>お問い合わせはメールで: email (at) domainname.com</p>
',
	'_PWT_HOME_BODY' => '
<p><strong>以下の文章はサンプルです。内容を適切に書き換えてください。</strong></p>
<p>いろはにほへと ちりぬるを わかよたれそ つねならむ うゐのおくやま けふこえて あさきゆめみし ゑひもせす</p>
<p>色は匂へど 散りぬるを 我が世誰ぞ 常ならむ 有為の奥山 今日越えて 浅き夢見じ 酔ひもせず</p>
<p>あめ つち ほし そら やま かは みね たに くも きり むろ こけ ひと いぬ うへ すゑ ゆわ さる おふ せよ えのえを なれ ゐて</p>
',
	'__SELECT_FILTER_VERB' => 'が',
	'https://www.sixapart.com/movabletype/' => 'https://www.sixapart.jp/movabletype/',

## addons/Commercial.pack/lib/CustomFields/App/CMS.pm
	'Checkbox' => 'チェックボックス',
	'Create Custom Field' => 'カスタムフィールドの作成',
	'Customize the forms and fields for entries, pages, folders, categories, and users, storing exactly the information you need.' => '記事、ウェブページ、フォルダ、カテゴリ、ユーザーのフォームとフィールドをカスタマイズして、必要な情報を格納することができます。',
	'Date & Time' => '日付と時刻',
	'Date Only' => '日付',
	'Date and Time' => '日付と時刻',
	'Drop Down Menu' => 'ドロップダウン',
	'Embed Object' => '埋め込みオブジェクト',
	'Exclude Custom Fields' => 'カスタムフィールドの除外',
	'Movable Type' => 'Movable Type',
	'Multi-Line Text' => 'テキスト(複数行)',
	'Permission denied.' => '権限がありません。',
	'Please ensure all required fields have been filled in.' => '必須のフィールドに値が入力されていません。',
	'Please enter all allowable options for this field as a comma delimited list' => 'このフィールドで有効なすべてのオプションをカンマで区切って入力してください。',
	'Please enter valid URL for the URL field: [_1]' => 'URLを入力してください。[_1]',
	'Post Type' => '投稿タイプ',
	'Radio Buttons' => 'ラジオボタン',
	'Saving permissions failed: [_1]' => '権限を保存できませんでした: [_1]',
	'Show' => '表示件数',
	'Single-Line Text' => 'テキスト',
	'Time Only' => '時刻',
	'URL' => 'URL',
	'View image' => '表示',
	'You must select other type if object is the comment.' => 'コメントでない場合、他の種類を選択する必要があります。',
	'[_1] Fields' => '[_1]フィールド',
	'blog and the system' => 'ブログとシステム',
	'blog' => 'ブログ',
	'type' => '種類',
	'website and the system' => 'ウェブサイトとシステム',
	'website' => 'ウェブサイト',
	q{Invalid date '[_1]'; dates must be in the format YYYY-MM-DD HH:MM:SS.} => q{日時「[_1]」が不正です。日時はYYYY-MM-DD HH:MM:SSの形式で入力してください。},
	q{Please enter some value for required '[_1]' field.} => q{「[_1]」は必須です。値を入力してください。},
	q{The basename '[_1]' is already in use. It must be unique within this [_2].} => q{[_1]というベースネームはすでに使われています。[_2]内で重複しない値を入力してください。},
	q{The template tag '[_1]' is already in use.} => q{[_1]というタグは既に存在します。},
	q{The template tag '[_1]' is an invalid tag name.} => q{[_1]というタグ名は不正です。},
	q{[_1] '[_2]' (ID:[_3]) added by user '[_4]'} => q{[_4]が[_1]「[_2]」(ID:[_3])を追加しました。},
	q{[_1] '[_2]' (ID:[_3]) deleted by '[_4]'} => q{'[_4]'が[_1]'[_2]'(ID:[_3])を削除しました。},

## addons/Commercial.pack/lib/CustomFields/BackupRestore.pm
	'Done.' => '完了',
	'Importing asset associations found in custom fields ( [_1] ) ...' => 'カスタムフィールド([_1])に含まれるアセットとの関連付けを復元しています...',
	'Importing custom fields data stored in MT::PluginData...' => 'MT::PluginDataに保存されているカスタムフィールドのデータをインポートしています...',
	'Importing url of the assets associated in custom fields ( [_1] )...' => 'カスタムフィールド([_1])に含まれるアセットのURLを復元しています...',

## addons/Commercial.pack/lib/CustomFields/DataAPI/Callback.pm
	'Please enter valid option for the [_1] field: [_2]' => '名前: [_2] (種類: [_1] ) のオプションを選択してください。',
	q{Invalid date '[_1]'; dates should be real dates.} => q{日時「[_1]」が不正です。},

## addons/Commercial.pack/lib/CustomFields/DataAPI/Callback/Field.pm
	'A parameter "[_1]" is required.' => '"[_1]" パラメータは必須です。',
	'The systemObject "[_1]" is invalid.' => '不正なシステムオブジェクトです: [_1]',
	'The type "[_1]" is invalid.' => '不正な種類です: [_1]',

## addons/Commercial.pack/lib/CustomFields/DataAPI/Endpoint/v2/Field.pm
	'Invalid includeShared parameter provided: [_1]' => '無効なincludeSharedパラメータが指定されました: [_1]',
	'Removing [_1] failed: [_2]' => '[_1]を削除できませんでした: [_2]',
	'Saving [_1] failed: [_2]' => '[_1]を保存できませんでした: [_2]',

## addons/Commercial.pack/lib/CustomFields/Field.pm
	'Fields' => 'フィールド',
	'_CF_BASENAME' => 'ベースネーム',
	'__CF_REQUIRED_VALUE__' => '値',
	q{The '[_1]' of the template tag '[_2]' that is already in use in [_3] is [_4].} => q{'[_2]'というテンプレートタグが[_3]に既に存在していますが、[_1]が異なるため、重複して作成する事が出来ません。テンプレートタグ名を変えるか、[_1]を同じにする必要があります。([_1]: [_4])},
	q{The template tag '[_1]' is already in use in [_2]} => q{[_1]というタグは既に[_2]に存在します。},
	q{The template tag '[_1]' is already in use in the system level} => q{[_1]というタグは既にシステムに存在します。},
	q{The template tag '[_1]' is already in use in this site} => q{[_1]というタグは既にこのサイトに存在します。},

## addons/Commercial.pack/lib/CustomFields/Template/ContextHandlers.pm
	q{Are you sure you have used a '[_1]' tag in the correct context? We could not find the [_2]} => q{[_2]が見つかりませんでした。[_1]タグを正しいコンテキストで使用しているか確認してください。},
	q{You used an '[_1]' tag outside of the context of the correct content; } => q{[_1]タグを正しいコンテキストで使用していません。},

## addons/Commercial.pack/lib/CustomFields/Theme.pm
	'Conflict of [_1] "[_2]" with [_3]' => '[_3] と[_1]「[_2]」が衝突しています',
	'Template Tag' => 'テンプレートタグ',
	'[_1] custom fields' => 'カスタムフィールド: [_1]',
	'a field on system wide' => 'システム全体のカスタムフィールド',
	'a field on this blog' => 'このブログのカスタムフィールド',

## addons/Commercial.pack/lib/CustomFields/Upgrade.pm
	'Moving metadata storage for pages...' => 'ウェブページのメタデータ格納先を変更しています...',
	'Removing CustomFields display-order from plugin data...' => 'カスタムフィールドの古い並び順を削除しています...',
	'Removing unlinked CustomFields...' => '不要なカスタムフィールドを削除しています。',

## addons/Commercial.pack/lib/CustomFields/Util.pm
	'Cloning fields for blog:' => 'カスタムフィールドを複製しています:',
	'[_1] records processed.' => '[_1]レコードを処理しました。',
	'[_1] records processed...' => '[_1]レコードを処理しました...',

## addons/Commercial.pack/templates/professional/blog/about_this_page.mtml
	'<a href="[_1]">[_2]</a> is the next archive.' => '次のアーカイブは<a href="[_1]">[_2]</a>です。',
	'<a href="[_1]">[_2]</a> is the next category.' => '次のカテゴリは<a href="[_1]">[_2]</a>です。',
	'<a href="[_1]">[_2]</a> is the next entry in this blog.' => '次の記事は「<a href="[_1]">[_2]</a>」です。',
	'<a href="[_1]">[_2]</a> is the previous archive.' => '前のアーカイブは<a href="[_1]">[_2]</a>です。',
	'<a href="[_1]">[_2]</a> is the previous category.' => '前のカテゴリは<a href="[_1]">[_2]</a>です。',
	'<a href="[_1]">[_2]</a> was the previous entry in this blog.' => 'ひとつ前の記事は「<a href="[_1]">[_2]</a>」です。',
	'About Archives' => 'このページについて',
	'About this Archive' => 'このアーカイブについて',
	'About this Entry' => 'この記事について',
	'Find recent content on the <a href="[_1]">main index</a> or look in the <a href="[_2]">archives</a> to find all content.' => '最近のコンテンツは<a href="[_1]">インデックスページ</a>で見られます。過去に書かれたものは<a href="[_2]">アーカイブのページ</a>で見られます。',
	'Find recent content on the <a href="[_1]">main index</a>.' => '最近のコンテンツは<a href="[_1]">インデックスページ</a>で見られます。',
	'This page contains a single entry by [_1] published on <em>[_2]</em>.' => 'このページは、[_1]が[_2]に書いた記事です。',
	'This page contains links to all of the archived content.' => 'このページには過去に書かれたすべてのコンテンツが含まれています。',
	'This page is an archive of entries from <strong>[_1]</strong> listed from newest to oldest.' => 'このページには、<strong>[_1]</strong>に書かれた記事が新しい順に公開されています。',
	'This page is an archive of entries in the <strong>[_1]</strong> category from <strong>[_2]</strong>.' => 'このページには、<strong>[_2]</strong>以降に書かれた記事のうち<strong>[_1]</strong>カテゴリに属しているものが含まれています。',
	'This page is an archive of recent entries in the <strong>[_1]</strong> category.' => 'このページには、過去に書かれた記事のうち<strong>[_1]</strong>カテゴリに属しているものが含まれています。',
	'This page is an archive of recent entries written by <strong>[_1]</strong> in <strong>[_2]</strong>.' => 'このページには、<strong>[_1]</strong>が<strong>[_2]</strong>に書いた記事が含まれています。',
	'This page is an archive of recent entries written by <strong>[_1]</strong>.' => 'このページには、<strong>[_1]</strong>が最近書いた記事が含まれています。',

## addons/Commercial.pack/templates/professional/blog/archive_index.mtml
	'Archives' => 'アーカイブ',

## addons/Commercial.pack/templates/professional/blog/archive_widgets_group.mtml
	'This is a custom set of widgets that serve different content depending on the type of archive in which it is included. More info: [_1]' => 'アーカイブの種類に応じて異なる内容を表示するように設定されたウィジェットです。詳細: [_1]',

## addons/Commercial.pack/templates/professional/blog/author_archive_list.mtml
	'Authors' => 'ユーザー',
	'[_1] ([_2])' => '[_1] ([_2])',

## addons/Commercial.pack/templates/professional/blog/calendar.mtml
	'Fri' => '金',
	'Friday' => '金曜日',
	'Mon' => '月',
	'Monday' => '月曜日',
	'Monthly calendar with links to daily posts' => 'リンク付きのカレンダー',
	'Sat' => '土',
	'Saturday' => '土曜日',
	'Sun' => '日',
	'Sunday' => '日曜日',
	'Thu' => '木',
	'Thursday' => '木曜日',
	'Tue' => '火',
	'Tuesday' => '火曜日',
	'Wed' => '水',
	'Wednesday' => '水曜日',

## addons/Commercial.pack/templates/professional/blog/comment_detail.mtml
	'[_1] replied to <a href="[_2]">comment from [_3]</a>' => '[_1]から<a href="[_2]">[_3]</a>への返信',

## addons/Commercial.pack/templates/professional/blog/comment_form.mtml
	'(You may use HTML tags for style)' => '(スタイル用のHTMLタグを使えます)',
	'Cancel' => 'キャンセル',
	'Email Address' => '電子メール',
	'Leave a comment' => 'コメントする',
	'Preview' => 'プレビュー',
	'Remember personal info?' => 'サインイン情報を記憶',
	'Replying to comment from [_1]' => '[_1]からのコメントに返信',
	'Submit' => '投稿',

## addons/Commercial.pack/templates/professional/blog/comment_preview.mtml
	'Previewing your Comment' => 'コメントのプレビュー',

## addons/Commercial.pack/templates/professional/blog/comment_response.mtml
	'Back' => '戻る',
	'Comment Submission Error' => 'コメント投稿エラー',
	'Confirmation...' => '確認',
	'Return to the <a href="[_1]">original entry</a>.' => '<a href="[_1]">元の記事</a>に戻る',
	'Thank you for commenting.' => 'コメントありがとうございます。',
	'Your comment has been received and held for review by a blog administrator.' => 'コメントは現在承認されるまで公開を保留されています。',
	'Your comment has been submitted!' => 'コメントを投稿しました。',
	'Your comment submission failed for the following reasons: [_1]' => 'コメントを投稿できませんでした。エラー: [_1]',

## addons/Commercial.pack/templates/professional/blog/comments.mtml
	'# Comments' => 'コメント(#)',
	'1 Comment' => 'コメント(1)',
	'Next' => '次',
	'No Comments' => 'コメント(0)',
	'Previous' => '前',
	'The data is modified by the paginate script' => 'ページネーションスクリプトによって変更されています。',

## addons/Commercial.pack/templates/professional/blog/creative_commons.mtml
	'This blog is licensed under a <a href="[_1]">Creative Commons License</a>.' => 'このブログは<a href="[_1]">クリエイティブ・コモンズ</a>でライセンスされています。',

## addons/Commercial.pack/templates/professional/blog/current_author_monthly_archive_list.mtml
	'[_1]: Monthly Archives' => '[_1]: 月別アーカイブ',

## addons/Commercial.pack/templates/professional/blog/current_category_monthly_archive_list.mtml
	'[_1]' => '[_1]',

## addons/Commercial.pack/templates/professional/blog/date_based_author_archives.mtml
	'Author Daily Archives' => '日別ユーザーアーカイブ',
	'Author Monthly Archives' => '月別ユーザーアーカイブ',
	'Author Weekly Archives' => '週別ユーザーアーカイブ',
	'Author Yearly Archives' => '年別ユーザーアーカイブ',

## addons/Commercial.pack/templates/professional/blog/date_based_category_archives.mtml
	'Category Daily Archives' => '日別カテゴリアーカイブ',
	'Category Monthly Archives' => '月別カテゴリアーカイブ',
	'Category Weekly Archives' => '週別カテゴリアーカイブ',
	'Category Yearly Archives' => '年別カテゴリアーカイブ',

## addons/Commercial.pack/templates/professional/blog/dynamic_error.mtml
	'Page Not Found' => 'ページが見つかりません。',

## addons/Commercial.pack/templates/professional/blog/entry_listing.mtml
	'Recently by <em>[_1]</em>' => '<em>[_1]</em>の最近の記事',
	'Recently in <em>[_1]</em> Category' => '<em>[_1]</em>の最近の記事',
	'[_1] Archives' => '[_1]アーカイブ',

## addons/Commercial.pack/templates/professional/blog/entry_metadata.mtml
	'# TrackBacks' => 'トラックバック(#)',
	'1 TrackBack' => 'トラックバック(1)',
	'By [_1] on [_2]' => '[_1] ([_2])',

## addons/Commercial.pack/templates/professional/blog/entry_summary.mtml
	'Continue reading <a href="[_1]" rel="bookmark">[_2]</a>.' => '続きを読む: <a href="[_1]" rel="bookmark">[_2]</a>',

## addons/Commercial.pack/templates/professional/blog/footer_links.mtml
	'Home' => 'ホーム',
	'Links' => 'リンク',

## addons/Commercial.pack/templates/professional/blog/javascript.mtml
	'Edit' => '編集',
	'Replying to <a href="[_1]" onclick="[_2]">comment from [_3]</a>' => '<a href="[_1]" onclick="[_2]">[_3]からのコメント</a>に返信',
	'Signing in...' => 'サインインします...',
	'Thanks for signing in, __NAME__. ([_1]sign out[_2])' => '__NAME__としてサインインしています。([_1]サインアウト[_2])',
	'The sign-in attempt was not successful; Please try again.' => 'サインインできませんでした。',
	'You do not have permission to comment on this blog. ([_1]sign out[_2])' => 'このブログにコメントする権限を持っていません。([_1]サインアウトする[_2])',
	'Your session has expired. Please sign in again to comment.' => 'セッションの有効期限が切れています。再度サインインしてください。',
	'[_1]Sign in[_2] to comment, or comment anonymously.' => 'コメントする前に[_1]サインイン[_2]することもできます。',
	'[_1]Sign in[_2] to comment.' => 'コメントするにはまず[_1]サインイン[_2]してください。',
	'[quant,_1,day,days] ago' => '[quant,_1,日,日]前',
	'[quant,_1,hour,hours] ago' => '[quant,_1,時間,時間]前',
	'[quant,_1,minute,minutes] ago' => '[quant,_1,分,分]前',
	'moments ago' => '直前',

## addons/Commercial.pack/templates/professional/blog/main_index_widgets_group.mtml
	'This is a custom set of widgets that only appear on the homepage (or "main_index"). More info: [_1]' => 'main_indexのテンプレートだけに表示されるように設定されているウィジェットのセットです。詳細: [_1]',

## addons/Commercial.pack/templates/professional/blog/monthly_archive_dropdown.mtml
	'Select a Month...' => '月を選択...',

## addons/Commercial.pack/templates/professional/blog/monthly_archive_list.mtml
	'[_1] <a href="[_2]">Archives</a>' => '[_1] <a href="[_2]">アーカイブ</a>',

## addons/Commercial.pack/templates/professional/blog/openid.mtml
	'Learn more about OpenID' => 'OpenIDについて',
	'[_1] accepted here' => '[_1]対応しています',
	'http://www.sixapart.com/labs/openid/' => 'https://www.sixapart.jp/about/openid.html',

## addons/Commercial.pack/templates/professional/blog/pages_list.mtml
	'Pages' => 'ウェブページ',

## addons/Commercial.pack/templates/professional/blog/powered_by_footer.mtml
	'_POWERED_BY' => 'Powered by <a href="https://www.sixapart.jp/movabletype/"><$MTProductName$></a>',

## addons/Commercial.pack/templates/professional/blog/recent_comments.mtml
	'<a href="[_1]">[_2] commented on [_3]</a>: [_4]' => '<a href="[_1]">[_2] から [_3] に対するコメント</a>: [_4]',

## addons/Commercial.pack/templates/professional/blog/search_results.mtml
	'By default, this search engine looks for all of the specified words in any order. To search for an exact phrase, enclose the phrase in quotes:' => 'すべての単語が順序に関係なく検索されます。フレーズで検索したいときは引用符で囲んでください。',
	'Instructions' => '例',
	'No results found for &ldquo;[_1]&rdquo;.' => '「[_1]」と一致する結果は見つかりませんでした。',
	'Results matching &ldquo;[_1]&rdquo;' => '「[_1]」と一致するもの',
	'Results tagged &ldquo;[_1]&rdquo;' => 'タグ「[_1]」が付けられているもの',
	'The search engine also supports the AND, OR, and NOT boolean operators:' => 'AND、OR、NOTを入れることで論理検索を行うこともできます。',
	'movable type' => 'movable type',
	'personal OR publishing' => '個人 OR 出版',
	'publishing NOT personal' => '個人 NOT 出版',

## addons/Commercial.pack/templates/professional/blog/signin.mtml
	'You are signed in as ' => 'ユーザー名:',
	'You do not have permission to sign in to this blog.' => 'このブログにサインインする権限がありません。',
	'sign out' => 'サインアウト',

## addons/Commercial.pack/templates/professional/blog/syndication.mtml
	'Feed of results matching &ldquo;[_1]&ldquo;' => '「[_1]」を検索した結果のフィード',
	'Feed of results tagged &ldquo;[_1]&ldquo;' => 'タグ「[_1]」のフィード',
	'Subscribe to a feed of all future entries matching &ldquo;[_1]&ldquo;' => 'タグ「[_1]」を購読',
	'Subscribe to a feed of all future entries tagged &ldquo;[_1]&ldquo;' => '「[_1]」の検索結果を購読',
	'Subscribe to feed' => '購読する',
	q{Subscribe to this blog's feed} => q{このブログを購読},

## addons/Commercial.pack/templates/professional/blog/trackbacks.mtml
	'<a href="[_1]">[_2]</a> from [_3] on <a href="[_4]">[_5]</a>' => '[_3] - <a href="[_1]">[_2]</a> (<a href="[_4]">[_5]</a>)',
	'No TrackBacks' => 'トラックバック(0)',
	'TrackBack URL: [_1]' => 'トラックバックURL: [_1]',
	'[_1] <a href="[_2]">Read More</a>' => '[_1] <a href="[_2]">続きを読む</a>',

## addons/Commercial.pack/templates/professional/website/blogs.mtml
	'Entries ([_1]) Comments ([_2])' => '記事([_1]) コメント([_2])',

## addons/Commercial.pack/templates/professional/website/comment_response.mtml
	'Return to the <a href="[_1]">original page</a>.' => '<a href="[_1]">元のページ</a>に戻る',

## addons/Commercial.pack/templates/professional/website/recent_entries_expanded.mtml
	'By [_1] | Comments ([_2])' => '[_1] | コメント([_2])',
	'on [_1]' => '[_1]ブログ上',

## addons/Commercial.pack/templates/professional/website/search.mtml
	'Case sensitive' => '大文字/小文字を区別する',
	'Regex search' => '正規表現',

## addons/Commercial.pack/templates/professional/website/search_results.mtml
	'By default, this search engine looks for all words in any order. To search for an exact phrase, enclose the phrase in quotes:' => 'すべての単語が順序に関係なく検索されます。フレーズで検索したいときは引用符で囲んでください。',

## addons/Commercial.pack/templates/professional/website/syndication.mtml
	q{Subscribe to this website's feed} => q{ウェブサイトを購読},

## addons/Commercial.pack/tmpl/asset-chooser.tmpl
	'Choose [_1]' => '[_1]を選択',
	'Remove [_1]' => '[_1]を削除する',
	'View' => '表示',

## addons/Commercial.pack/tmpl/category_fields.tmpl
	'Show These Fields' => 'フィールド表示',

## addons/Commercial.pack/tmpl/edit_field.tmpl
	'Choose the system object where this custom field should appear.' => 'フィールドを追加するオブジェクトを選択してください。',
	'Default' => '既定値',
	'Delete this field (x)' => 'フィールドを削除 (x)',
	'Edit Custom Field' => 'カスタムフィールドの編集',
	'Example Template Code' => 'テンプレートの例',
	'Must the user enter data into this custom field before the object may be saved?' => 'フィールドに値は必須ですか?',
	'Options' => 'オプション',
	'Required?' => '必須?',
	'Save this field (s)' => 'このフィールドを保存 (s)',
	'Save' => '保存',
	'Select...' => '選択してください',
	'Show In These [_1]' => '[_1]に表示',
	'The basename must be unique within this [_1].' => 'ベースネームは、[_1]内で重複しない値を入力してください。',
	'The selected field(s) has been deleted from the database.' => '選択されたフィールドはデータベースから削除されました。',
	'You must enter information into the required fields highlighted below before the custom field can be created.' => 'すべての必須フィールドに値を入力してください。',
	'You must save this custom field before setting a default value.' => '既定の値を設定する前に、このカスタムフィールドを保存する必要があります。',
	'Your changes have been saved.' => '変更を保存しました。',
	'field' => 'フィールド',
	'fields' => 'フィールド',
	q{Warning: Changing this field's basename may require changes to existing templates.} => q{警告: このフィールドのベースネームを変更すると、テンプレートにも修正が必要になることがあります。},

## addons/Commercial.pack/tmpl/export_field.tmpl
	'Object' => 'オブジェクト',
	'Select All' => 'すべて選択',
	'Select' => '選択',
);

1;
