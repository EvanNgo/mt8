# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

package LicenseVerification::L10N::ja;

use strict;
use warnings;

use base 'LicenseVerification::L10N::en_us';
use vars qw( %Lexicon );

%Lexicon = (
## plugins/LicenseVerification/lib/LicenseVerification/App.pm 
	'Your maintenance period will expire soon on [_1]' => 'メンテナンスの期限が近づいています（[_1]）',
	'Your maintenance period has expired on [_1]' => 'メンテナンスの期限が切れています（[_1]）',

## plugins/LicenseVerification/lib/LicenseVerification/App/CMS.pm 
	'Could not verify your license key.' => 'ライセンス番号の確認ができませんでした',
	'License or User not found' => 'ライセンスキーまたは SAID が見つかりません',
	'Network connection issue.' => '現在のネットワーク構成ではライセンス番号の登録ができません',
	'The id of your license key is wrong' => '登録情報が異なります',
	'License key is required.' => 'ライセンスキーは必須です。',
	'SAID is required.' => 'SAID は必須です。',
	'Invalid parameters' => 'パラメータが不正です',
	'Your license key is already in use' => 'すでに他の Movable Type に登録されています',

## plugins/LicenseVerification/config.yaml
	'This plugin provides your Movable Type License Verification.' => 'このプラグインは Movable Type のライセンス管理を提供します。',

## plugins/LicenseVerification/tmpl/admin2023/edit_license_verification.tmpl
	'Are you sure you want to delete your license key?' => 'ライセンスキーの登録情報を削除しますか？',
	'Auto-renew Available' => '自動継続中',
	'Disabled' => '無効',
	'Download Availability' => 'ダウンロードの可否',
	'Download Expiration Date' => 'ダウンロード期限',
	'Enabled' => '有効',
	'License Key' => 'ライセンスキー',
	'License Verification' => 'ライセンス確認',
	'Licensed Product Name' => 'プロダクト名',
	'Licensee Name' => '登録社名',
	'Press following Delete button to delete your data.' => '登録内容を削除する場合には次の削除ボタンを押してください',
	'Registered Count' => '登録本数',
	'Registered Date' => '登録日時',
	'Technical Support Availability' => 'テクニカルサポート',
	'Technical Support Expiration Date' => 'メンテナンス期限',
	'The ID for signing in to <a href="[_1]" target="_blank">Six Apart User Site</a>' => '<a href="[_1]" target="_blank">Six Apart User Site</a>へログインするときのIDです',
	'You can check your license key if you visit to <a href="[_1]" target="_blank">Six Apart User Site</a>' => '<a href="[_1]" target="_blank">Six Apart User Site</a>でライセンスキーを確認できます',
);

1;
__END__
