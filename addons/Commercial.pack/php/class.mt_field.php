<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

require_once("class.baseobject.php");

/***
 * Class for mt_field
 */
class Field extends BaseObject
{
    public $_table = 'mt_field';
    protected $_prefix = "field_";
    protected $_has_meta = false;

    public $field_id;
    public $field_blog_id;
    public $field_name;
    public $field_description;
    public $field_obj_type;
    public $field_type;
    public $field_tag;
    public $field_default;
    public $field_options;
    public $field_required;
    public $field_basename;
}

