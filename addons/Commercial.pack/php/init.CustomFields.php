<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

# Original Copyright (c) 2005-2007 Arvind Satyanarayan

$mt = MT::get_instance();
$ctx = &$mt->context();

define('CUSTOMFIELDS_ENABLED', 1);

global $customfield_types;
$customfield_types or $customfield_types = array();
init_core_customfield_types();

# Because it's hard to map tags back to their fields, we store them in a hash
global $customfields_custom_handlers;
$customfields_custom_handlers = array();

$dynamic_functions = '';

# Loop through fields and register the custom template tag handlers
require_once('class.baseobject.php');
require_once('class.mt_field.php');
$fld = new Field();
$fields = $fld->Find('1 = 1');
if ( !empty( $fields ) ) {
foreach ($fields as $field) {
    $tag_name = strtolower($field->field_tag);
    $customfields_custom_handlers[$tag_name] = $field;

    $col_type = isset($customfield_types[$field->field_type]['column_def'])
                ? $customfield_types[$field->field_type]['column_def'] : null;
    if ($col_type) {
        BaseObject::install_meta($field->field_obj_type, 'field.' . $field->field_basename, $col_type);
    }

    $fn_name = $field->field_id;
    $dynamic_functions = <<<CODE
function customfield_$fn_name(\$args, &\$ctx) {
    return _hdlr_customfield_value(\$args, \$ctx->smarty, '$tag_name');
}
CODE;
    eval($dynamic_functions);
    $ctx->add_tag($tag_name, 'customfield_' . $fn_name);

    if ($field->field_type && preg_match('/^video|image|file|audio/', $field->field_type)) {
        $dynamic_functions = <<<CODE
function customfield_asset_$fn_name(\$args, \$content, &\$ctx, &\$repeat) {
    return _hdlr_customfield_asset(\$args, \$content, \$ctx->smarty, \$repeat, '$tag_name');
}
CODE;
        eval($dynamic_functions);
        $ctx->add_container_tag($tag_name . $field->type, 'customfield_asset_' . $fn_name);
        $ctx->add_container_tag($tag_name . 'asset', 'customfield_asset_' . $fn_name);
    }
}
}

# Loop through all the custom field object types and register the MT*CustomField(s) tags
$customfield_object_types = array(
    'entry', 'page', 'category', 'folder', 'author', 'template', 'comment',
    'video', 'file', 'asset', 'image', 'audio', 'website', 'blog');
foreach ($customfield_object_types as $type) {
    $dynamic_functions = <<<CODE
function customfields_$type(\$args, \$content, &\$ctx, &\$repeat) {
    return _hdlr_customfields(\$args, \$content, \$ctx, \$repeat, '$type');
}

function customfield_name_$type(\$args, &\$ctx) {
    return _hdlr_customfield_name(\$args, \$ctx);
}

function customfield_description_$type(\$args, &\$ctx) {
    return _hdlr_customfield_description(\$args, \$ctx);
}

function customfield_value_$type(\$args, &\$ctx) {
    return _hdlr_customfield_value(\$args, \$ctx);
}
CODE;
    eval($dynamic_functions);
    $ctx->add_container_tag($type.'customfields', 'customfields_'.$type);
    $ctx->add_tag($type.'customfieldname', 'customfield_name_'.$type);
    $ctx->add_tag($type.'customfielddescription', 'customfield_description_'.$type);
    $ctx->add_tag($type.'customfieldvalue', 'customfield_value_'.$type);
}

$ctx->add_tag('customfieldname', '_hdlr_customfield_name');
$ctx->add_tag('customfieldbasename', '_hdlr_customfield_basename');
$ctx->add_tag('customfielddescription', '_hdlr_customfield_description');
$ctx->add_tag('customfieldvalue', '_hdlr_customfield_value');
$ctx->add_tag('customfieldhtml', '_hdlr_customfield_html');

$ctx->add_conditional_tag('mtcustomfieldisrequired', '_hdlr_customfield_is_required');

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_obj
function _hdlr_customfield_obj(&$ctx, $obj_type) {
    # Pages and folders stash as entries and categories
    # respectively so lets change $obj_type
    if($obj_type == 'website') {
        $blog = $ctx->stash('blog');
        if(!empty($blog)) {
            $obj = $blog->is_blog() ? $blog->website() : $blog;
        }
    } else {
        if($obj_type == 'page')
            $obj_type = 'entry';
        elseif($obj_type == 'folder')
            $obj_type = 'category';
        elseif($obj_type == 'image' ||
               $obj_type == 'audio' ||
               $obj_type == 'video' ||
               $obj_type == 'file')
            $obj_type = 'asset';

        $obj = $ctx->stash($obj_type);
    }

    # In PHP, we only need to test for this because archive_category doesn't exist
    if(!$obj) {
        $entry = $ctx->stash('entry');
        if(!$entry) return '';

        if ($obj_type == 'category') {
            $obj = $entry->category();
        }

        if ($obj_type == 'author') {
            $entry_id = $entry->entry_id;
            # We need to cache this puppy as much as possible
            $obj = $ctx->stash("entry_{$entry_id}_author");
            if(!$obj) {
                $author_id = $entry->entry_author_id;
                require_once('class.mt_author.php');
                $author = new Author();
                $obj = $author->LoadByIntId($author_id);
                $ctx->stash("entry_{$entry_id}_author", $obj[0]);
            }
        }
    }

    return $obj;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfields
function _hdlr_customfields($args, $content, &$ctx, &$repeat, $obj_type = null) {
    $localvars = array('fields', '_fields_counter', 'field', 'blog', 'blog_id');
    if (!isset($content)) {
        $ctx->localize($localvars);
        global $mt;
        $blog_id = $ctx->stash('blog_id');

        $exclude = array();
        if (isset($args['exclude'])) {
            foreach ( preg_split('/\s*,\s*/', $args['exclude']) as $f )
                $exclude[strtolower($f)] = 1;
        }

        $include = array();
        if (isset($args['include'])) {
            foreach ( preg_split('/\s*,\s*/', $args['include']) as $f )
                array_push($include, "'" . $mt->db()->escape($f) . "'");
        }

        require_once('class.mt_field.php');
        $field = new Field();
        $sql = "field_blog_id in (0, $blog_id) and field_obj_type = '$obj_type'";
        if ( count($include) ) {
            $sql .= " and field_name in (";
            $sql .= implode( ',', $include );
            $sql .= ")";
        }

        $all_fields = $field->Find($sql);
        if ( empty( $all_fields ) )
            $all_fields = array();

        $fields = array();
        foreach ( $all_fields as $f ) {
            if ( array_key_exists( strtolower($f->field_name), $exclude ) )
                continue;
            array_push( $fields, $f );
        }
        $ctx->stash('fields', $fields);
        $counter = 0;
    } else {
        $fields = $ctx->stash('fields');
        $counter = $ctx->stash('_fields_counter');
    }
    if (is_array($fields) && $counter < count($fields)) {
        $field = $fields[$counter];
        $ctx->stash('field', $field);
        $ctx->stash('_fields_counter', $counter + 1);
        $repeat = true;
    } else {
        $ctx->restore($localvars);
        $repeat = false;
    }
    return $content;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_name
function _hdlr_customfield_name($args, &$ctx) {
    $field = $ctx->stash('field');
    return $field->field_name;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_basename
function _hdlr_customfield_basename($args, &$ctx) {
    $field = $ctx->stash('field');
    return $field->field_basename;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_description
function _hdlr_customfield_description($args, &$ctx) {
    $field = $ctx->stash('field');
    return $field->field_description;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_value
function _hdlr_customfield_value($args, &$ctx, $tag = null) {
    global $customfields_custom_handlers;
    $field = $ctx->stash('field');
    $field or $field = $customfields_custom_handlers[$tag];
    if(!$field) return '';

    $obj = _hdlr_customfield_obj($ctx, $field->field_obj_type);
    if(!isset($obj) || empty($obj)) return $field->default ? $field->default : '';

    $real_type = $field->field_obj_type;
    if ($real_type == 'folder')
        $real_type = 'category';
    elseif ($real_type == 'page')
        $real_type = 'entry';

    $text = $obj->{$obj->_prefix . 'field.' . $field->field_basename};
    if ($text && preg_match('/\smt:asset-id="\d+"/', $text) && !$args['no_asset_cleanup']) {
        require_once("MTUtil.php");
        $text = asset_cleanup($text);
    }

    if($field->field_type == 'textarea') {
        if ($obj instanceof Entry) {
            $cb = $obj->entry_convert_breaks;
        } elseif ($obj instanceof Blog) {
            $cb = $obj->blog_convert_paras;
        }
        if (isset($args->convert_breaks)) {
            $cb = $args->convert_breaks;
        } elseif (!isset($cb)) {
            $blog = $ctx->stash('blog');
            $cb = $blog->blog_convert_paras;
        }
        if ($cb) {
            if (($cb == '1') || ($cb == '__default__')) {
                # alter EntryBody, EntryMore in the event that
                # we're doing convert breaks
                $cb = 'convert_breaks';
            }
            require_once 'MTUtil.php';
            $text = apply_text_filter($ctx, $text, $cb);
        }
    }

    if (array_key_exists('label', $args) && $args['label']) {
        $value_label = '';
        $type_obj = $customfield_types[$field->field_type];
        if (array_key_exists('options_delimiter', $type_obj)) {
            $option_loop = array();
            $expr = '\s*' . preg_quote($type_obj->options_delimiter) . '\s*';
            $options = preg_split('/' . $expr . '/', $field->field_options);
            foreach ($options as $option) {
                $label = $option;
                if (preg_match('/=/', $option))
                    list($option, $label) = preg_split('/\s*=\s*/', $option, 2);
                if ($text == $option) {
                    $value_label = $label;
                    break;
                }
            }
        }
        $text = $value_label;
    }

    if($field->field_type == 'datetime') {
        $text = $text ? preg_replace('/\D/', '', $text) : '';
        if (($text == '') or ($text == '00000000'))
            return '';
        if (strlen($text) == 8) {
            $text .= '000000';
        }
        if ( empty($args['form_field']) ) {
            $args['ts'] = $text;
            if ($field->field_options == 'date') {
                if ( !isset( $args['format'] ) )
                    $args['format'] = '%x';
            } elseif ($field->field_options == 'time') {
                if ( !isset( $args['format'] ) )
                    $args['format'] = '%X';
            }
            return $ctx->_hdlr_date($args, $ctx);
        }
    }

    return $text;
}

function _hdlr_customfield_asset($args, $content, &$ctx, &$repeat, $tag = null) {
    $localvars = array('assets', 'asset', '_assets_counter', 'blog', 'blog_id');
    if (!isset($content)) {
        $ctx->localize($localvars);
        $blog_id = $ctx->stash('blog_id');

        $args['no_asset_cleanup'] = 1;
        $value = _hdlr_customfield_value($args, $ctx, $tag);

        $args['blog_id'] = $blog_id;
        if(preg_match('!<form[^>]*?\smt:asset-id=["\'](\d+)["\'][^>]*?>(.+?)</form>!is', $value, $matches)) {
            $args['id'] = $matches[1];
        } else {
            $ctx->restore($localvars);
            $repeat = false;
            return '';
        }

        $assets = $ctx->mt->db()->fetch_assets($args);
        $ctx->stash('assets', $assets);
        $counter = 0;
    } else {
        $assets = $ctx->stash('assets');
        $counter = $ctx->stash('_assets_counter');
    }
    if (is_array($assets) && $counter < count($assets)) {
        $asset = $assets[$counter];
        $ctx->stash('asset', $asset);
        $ctx->stash('_assets_counter', $counter + 1);
        $repeat = true;
    } else {
        $ctx->restore($localvars);
        $repeat = false;
    }
    return $content;
}

function _hdlr_customfield_html($args, &$ctx) {
    global $customfield_types;

    $field = $ctx->stash('field');
    $type = $field->field_type;
    $basename = $field->field_basename;

    $type_obj = $customfield_types[$type];
    if (!$type_obj) return '';

    $row = $field->GetArray();
    $row['field_blog_id'] or $row['field_blog_id'] = 0;
    $row['field_value'] = _hdlr_customfield_value(array('form_field' => 1), $ctx);
    if (array_key_exists('options_delimiter', $type_obj)) {
        $option_loop = array();
        $expr = '\s*' . preg_quote($type_obj['options_delimiter']) . '\s*';
        $options = preg_split('/' . $expr . '/', $field->field_options);
        foreach ($options as $option) {
            $label = $option;
            if (preg_match('/=/', $option))
                list($option, $label) = preg_split('/\s*=\s*/', $option, 2);
            $option_row = array( 'option' => $option, 'label' => $label );
            $option_row['is_selected'] = false;
            if ( !empty($row['field_value']) ) {
                if ( ($row['field_value'] == $option) )
                    $option_row['is_selected'] = true;
            } elseif ( !empty($row['field_default']) ) {
                if ( $row['field_default'] == $option )
                    $option_row['is_selected'] = true;
            }
            $option_loop[] = $option_row;
        }
        $row['option_loop'] = $option_loop;
    }
    $row['show_field'] = ($field->field_obj_type == 'entry') ? 0 : 1;
    $row['show_hint'] = $type != 'checkbox' ? 1 : 0;
    $row['field_id'] = $row['field_name'] = "customfield_$basename";

    $fn = $type_obj['field_html'];
    if ( is_array($fn) ) {
        $buf = $fn[ $field->field_obj_type ] ?? null;
        if (!isset($buf))
            $buf = $fn['default'];
        $fn = $buf;
    }
    if (function_exists($fn)) {
        $contents = call_user_func_array($fn, array(&$ctx, $row));
    } else {
        $contents = '';
    }
    return $contents;
}

function customfield_html_textarea(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    return <<<EOT
<textarea name="$field_name" id="$field_id" class="text high">$field_value</textarea>
EOT;
}

function customfield_html_embed(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    return <<<EOT
<textarea name="$field_name" id="$field_id" class="text high">$field_value</textarea>
EOT;
}

function customfield_html_text(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    return <<<EOT
<input type="text" name="$field_name" id="$field_id" class="text" value="$field_value" />
EOT;
}

function customfield_html_checkbox(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    $field_description = encode_html($field_description);
    $checked = '';
    if ($field_value)
        $checked = ' checked="checked"';
    return <<<EOT
<input type="hidden" name="{$field_name}_cb_beacon" value="1" /><input type="checkbox" name="$field_name" value="1" id="$field_id"$checked class="cb" /> <label class="hint" for="$field_id">$field_description</label>
EOT;
}

function customfield_html_url(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    return <<<EOT
<input type="text" name="$field_name" id="$field_id" class="text" value="$field_value" />
EOT;
}

function customfield_html_datetime(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $blog = $ctx->stash('blog');
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);
    $ts = $field_value;
    $ts = preg_replace('/\D/', '', $ts);
    if ($ts != '') {
        $date = format_ts("%Y-%m-%d", $ts, $blog);
        $time = format_ts("%H:%M:%S", $ts, $blog);
    } else {
        $date = '';
        $time = '';
    }
    list($year, $month, $day) = $date ? explode('-', $date) : ['', '', ''];
    list($hour, $minute, $second) = $time ? explode(':', $time) : ['', '', ''];

    if ($field_options != 'time') {
        $mb = ($field_options != 'time' && $field_options != 'date') ? ' mb-2' : '';
        $html1 = <<<EOT
<input type="text" id="d_$field_name" class="entry-date text-date" name="d_$field_name" value="$date" placeholder="YYYY-MM-DD" />
EOT;
    } else {
        $html1 = <<<EOT
<input type="hidden" id="d_$field_name" name="d_$field_name" value="" />
EOT;
    }

    if ($field_options != 'date') {
        $html2 = <<<EOT
<input type="text" class="entry-time" name="t_$field_name" value="$time" placeholder="HH:mm:ss" />
EOT;
    } else {
        $html2 = <<<EOT
<input type="hidden" id="t_$field_name" name="t_$field_name" value="" />
EOT;
    }

    return <<<EOT
<span class="date-time-fields">
$html1
$html2
</span>
EOT;
}

function customfield_html_select(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);

    $loop = '';
    foreach ($option_loop as $option) {
        $opt = encode_html($option['option']);
        if ($option['is_selected'])
            $selected = " selected='selected'";
        else
            $selected = '';
        $loop .= <<<EOT
    <option value="$opt"$selected>$opt</option>

EOT;
    }


    return <<<EOT
<select name="$field_name" id="$field_id" class="se" mt:watch-change="1">
$loop
</select>
EOT;
}

function customfield_html_radio(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);

    $html = '';
    $i = 0;
    $html = '<ul class="custom-field-radio-list">' . "\n\n";

    foreach ($option_loop as $option) {
        $i++;
        $opt = encode_html($option['option']);
        if ($option['is_selected'])
            $selected = " checked='checked'";
        else
            $selected = '';
        $html .= <<<EOT
<li><input type="radio" name="$field_name" value="$opt" id="{$field_id}_{$i}"$selected class="rb" /> <label for="{$field_id}_{$i}">$opt</label></li>

EOT;
    }
    $html .= '</ul>';

    return $html;
}

function customfield_html_image(&$ctx, $param) {
    $param['asset_type'] = 'image';
    return customfield_html_asset($ctx, $param);
}
function customfield_html_video(&$ctx, $param) {
    $param['asset_type'] = 'video';
    return customfield_html_asset($ctx, $param);
}
function customfield_html_audio(&$ctx, $param) {
    $param['asset_type'] = 'audio';
    return customfield_html_asset($ctx, $param);
}
function customfield_html_file(&$ctx, $param) {
    $param['asset_type'] = 'file';
    return customfield_html_asset($ctx, $param);
}
function customfield_html_asset(&$ctx, $param) {
    extract($param);
    require_once("MTUtil.php");
    $field_name = encode_html($field_name);
    $field_value = encode_html($field_value);

    return <<<EOT
    <input type="file" name="file_$field_name" id="entry-file" class="fi" />
    <input type="hidden" name="type_$field_name" value="$asset_type" />
EOT;
}
function init_core_customfield_types() {
    global $customfield_types;
    $customfield_types['text'] = array(
        'field_html' => 'customfield_html_text',
        'column_def' => 'vchar_idx',
    );
    $customfield_types['textarea'] = array(
        'field_html' => 'customfield_html_textarea',
        'column_def' => 'vclob',
    );
    $customfield_types['checkbox'] = array(
        'field_html' => 'customfield_html_checkbox',
        'column_def' => 'vinteger_idx',
    );
    $customfield_types['url'] = array(
        'field_html' => 'customfield_html_url',
        'column_def' => 'vchar',
    );
    $customfield_types['datetime'] = array(
        'field_html' => 'customfield_html_datetime',
        'column_def' => 'vdatetime_idx',
    );
    $customfield_types['select'] = array(
        'field_html' => 'customfield_html_select',
        'options_delimiter' => ',',
        'column_def' => 'vchar_idx',
    );
    $customfield_types['radio'] = array(
        'field_html' => 'customfield_html_radio',
        'options_delimiter' => ',',
        'column_def' => 'vchar_idx',
    );
    $customfield_types['file'] = $customfield_types['asset'] = array(
        'field_html' => 'customfield_html_file',
        'column_def' => 'vclob',
    );
    $customfield_types['image'] = $customfield_types['asset.image'] = array(
        'field_html' => 'customfield_html_image',
        'column_def' => 'vclob',
    );
    $customfield_types['audio'] = $customfield_types['asset.audio'] = array(
        'field_html' => 'customfield_html_audio',
        'column_def' => 'vclob',
    );
    $customfield_types['video'] = $customfield_types['asset.video'] = array(
        'field_html' => 'customfield_html_video',
        'column_def' => 'vclob',
    );
    $customfield_types['post_type'] = array(
        'field_html' => array(
            'default' => 'customfield_html_text',
        ),
        'column_def' => 'vchar_idx',
    );
    $customfield_types['embed'] = array(
        'field_html' => array(
            'default' => 'customfield_html_embed',
            'sanitize' => 'customfield_embed_sanitize',
        ),
        'column_def' => 'vclob',
    );
}

function customfield_embed_sanitize($str) {
    return $str;
}

# PHP implementation of CustomFields::Template::ContextHandlers::_hdlr_customfield_is_required
function _hdlr_customfield_is_required($args, $content, &$ctx, &$repeat) {
    if (!isset($content)) {
        $field = $ctx->stash('field');
        $ok = $field->required == 1 ? 1 : 0;
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat, $ok);
    } else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }
}

