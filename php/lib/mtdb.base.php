<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

require_once('adodb-exceptions.inc.php');
require_once('adodb.inc.php');
if (!defined('ADODB_ASSOC_CASE')) define('ADODB_ASSOC_CASE', ADODB_ASSOC_CASE_LOWER);

require_once('adodb-active-record.inc.php');

abstract class MTDatabase {
    var $savedqueries = array();


    // Member variables
    protected $id;
    protected $conn;
    protected $serializer;
    protected $pdo_enabled = false;
    protected $has_distinct = true;

    // Cache variables
    protected $_entry_id_cache = array();
    protected $_comment_count_cache = array();
    protected $_ping_count_cache = array();
    protected $_cat_id_cache = array();
    protected $_tag_id_cache = array();
    protected $_blog_id_cache = array();
    protected $_entry_link_cache = array();
    protected $_cat_link_cache = array();
    protected $_archive_link_cache = array();
    protected $_entry_tag_cache = array();
    protected $_blog_tag_cache = array();
    protected $_asset_tag_cache = array();
    protected $_blog_asset_tag_cache = array();
    protected $_author_id_cache = array();
    protected $_category_set_id_cache = array();
    protected $_rebuild_trigger_cache = array();
    protected $_content_link_cache = array();
    protected $_adodb_quote_fieldnames = 'NATIVE';

    private $_cd_id_cache = array();
    private $_content_type_id_cache; // XXX consider changing the cache storage
    private $_content_field_id_cache;
    private $_cd_tag_cache;

    // Construction
    public function __construct($user, $password = '', $dbname = '', $host = '', $port = '', $sock = '', $retry = 3, $retry_int = 1) {
        global $ADODB_QUOTE_FIELDNAMES;
        $ADODB_QUOTE_FIELDNAMES = $this->_adodb_quote_fieldnames;
        $this->id = md5(uniqid('MTDatabase',true));
        $retry_cnt = 0;
        while ( ( empty($this->conn) || ( !empty($this->conn) && !$this->conn->IsConnected() ) ) && $retry_cnt++ < $retry ) {
            try {
                $this->connect($user, $password, $dbname, $host, $port, $sock);
            } catch (Exception $e ) {
                sleep( $retry_int );
            }
        }
        if ( empty($this->conn) || ( !empty($this->conn) && !$this->conn->IsConnected() ) ) {
            throw new MTDBException( $this->conn->ErrorMsg() , 0);
        }

        ADOdb_Active_Record::SetDatabaseAdapter($this->conn);
#        $this->conn->debug = true;
    }

    // Abstract method
    abstract protected function connect($user, $password = '', $dbname = '', $host = '', $port = '', $sock = '');
    abstract public function set_names($mt);

    // Utility method
    public function escape($str) {
        return substr($this->conn->Quote(stripslashes($str)), 1, -1);
    }

    public function has_distinct_support () {
        return $this->has_distinct;
    }

    public function &db() {
        return $this->conn;
    }

    public function execute($sql, $bindarr=false) {
        return $this->conn->Execute($sql, $bindarr);
    }

    public function decorate_column( $order ) {
        return $order;
    }

    public function binary_column( $column ) {
        return $column;
    }

    public function SelectLimit($sql, $limit = -1, $offset = -1, $bindarr=false) {
        return $this->conn->SelectLimit($sql, $limit, $offset, $bindarr);
    }

    public function unserialize($data) {
        if (!$this->serializer) {
            require_once("MTSerialize.php");
            $this->serializer = MTSerialize::get_instance();
        }
        return $this->serializer->unserialize($data);
    }

    public function serialize($data) {
        if (!$this->serializer) {
            require_once("MTSerialize.php");
            $this->serializer = MTSerialize::get_instance();
        }
        return $this->serializer->serialize($data);
    }

    public function parse_blog_ids( $blog_ids, $include_with_website = false ) {
        $ret = array();

        if ( empty($blog_ids) || $blog_ids == 'all')
            return $ret;

        if (preg_match('/-/', $blog_ids)) {
            # parse range blog ids out
            $list = preg_split('/\s*,\s*/', $blog_ids);
            foreach ($list as $item) {
                if (preg_match('/(\d+)-(\d+)/', $item, $matches)) {
                    for ($i = $matches[1]; $i <= $matches[2]; $i++) {
                        array_push( $ret, $i );
                    }
                } else if (ctype_digit($item)) {
                    array_push( $ret, $item );
                }
            }
        } elseif ( preg_match( '/\s*,\s*/', $blog_ids ) ) {
            $ret = preg_split( '/\s*,\s*/',
                               $blog_ids,
                               -1, PREG_SPLIT_NO_EMPTY);
        } elseif (
             ($blog_ids == 'site')
          || ($blog_ids == 'children')
          || ($blog_ids == 'siblings')
        ) {
            $mt = MT::get_instance();
            $ctx = $mt->context();
            $blog = $ctx->stash('blog');
            if (!empty($blog)) {
                require_once('class.mt_blog.php');
                $blog_class = new Blog();
                $blogs = $blog_class->Find("blog_parent_id = " . ( $blog->is_blog() ? $blog->parent_id : $blog->id) );
                if ( !empty( $blogs ) ) {
                    foreach($blogs as $b) {
                        array_push($ret, $b->id);
                    }
                }
                if ( $include_with_website ) {
                    $website = ( $blog->is_blog() ? $blog->website() : $blog);
                    array_push($ret, $website->id);
                }
            }
        } else {
            if ( is_numeric($blog_ids) )
                $blog_ids = strval($blog_ids);
            if ( ctype_digit( $blog_ids ) )
                 array_push( $ret, $blog_ids);
        }
        return $ret;
    }

    public function include_exclude_blogs(&$args) {

        trigger_error('function include_exclude_blogs is deprecated', E_USER_DEPRECATED);

        if ( empty( $args['include_parent_site'] ) && empty( $args['include_with_website'] ) )
            $include_with_website = false;
        else
            $include_with_website = true;

        $incl = null;
        $excl = null;
        if ( isset($args['include_sites'])
          || isset($args['blog_ids'])
          || isset($args['include_blogs'])
          || isset($args['site_ids'])
          || isset($args['include_websites']) )
        {
            // The following are aliased
            if (!empty($args['include_sites']))
                $incl = $args['include_sites'];
            elseif (!empty($args['blog_ids']))
                $incl = $args['blog_ids'];
            elseif (!empty($args['include_blogs']))
                $incl = $args['include_blogs'];
            elseif (!empty($args['site_ids']))
                $incl = $args['site_ids'];
            elseif (!empty($args['include_websites']))
                $incl = $args['include_websites'];
            $args['include_blogs'] = $incl;
            unset($args['include_sites']);
            unset($args['blog_ids']);
            unset($args['site_ids']);
            unset($args['include_websites']);
        }
        else if (isset($args['site_id'])) {
            $incl = $args['site_id'];
        }
        else if (isset($args['blog_id'])) {
            $incl = $args['blog_id'];
        }

        if (isset($args['exclude_sites']) || isset($args['exclude_blogs']) || isset($args['exclude_websites'])) {
            $excl = isset($args['exclude_sites']) ? $args['exclude_sites'] : null;
            $excl or $excl = (isset($args['exclude_blogs']) ? $args['exclude_blogs'] : null);
            $excl or $excl = (isset($args['exclude_websites']) ? $args['exclude_websites'] : null);

            if ( !isset( $args['include_blogs'] ) ) {
                # If only exclude_blogs supplied, set include_blogs as all
                $incl = 'all';
                $args['include_blogs'] = 'all';
            }
        }

        // Compute include_blogs
        if ( !empty($incl) )
            $incl = $this->parse_blog_ids( $incl, $include_with_website );
        if ( isset( $args['allows'] ) ) {
            if ( empty( $incl ) )
                $incl = $args['allows'];
            else
                $incl = array_intersect($incl, $args['allows']);
        }

        // Compute exclude_blogs
        if ( !empty($excl) )
            $excl = $this->parse_blog_ids( $excl );

        if ( isset( $args['denies'] ) ) {
            foreach ( $args['denies'] as $val )
                $denies[$val] = 1;
            if ( !empty($excl) ) {
                foreach ( $excl as $e ) {
                    if ( !array_key_exists( $e, $denies ) )
                        $denies[$e] = 1;
                }
            }
            $excl = array_keys( $denies );
        }

        if ( !empty($incl) && !empty($excl) ) {
            $incl = array_diff($incl, $excl);
            if ( empty( $incl ) ) {
                $mt = MT::get_instance();
                trigger_error( $mt->translate(
                        "When the exclude_blogs and include_blogs attributes are used together, the same blog IDs should not be listed as parameters to both of them."
                ) );
            } else {
                $incl = array_values( $incl );
                $excl = null; // remove all exclude pattern.
            }
        }

        if ( !empty($incl) ) {
            if ( count($incl) > 1 )
                return " in (" . implode(',', $incl) . ' )';
            else
                return " = " . array_shift($incl);
        } elseif ( !empty($excl) ) {
            return " not in (" . implode(',', $excl) . ' )';
        } else {
            if ( isset($args['include_blogs']) && strtolower($args['include_blogs']) == 'all') {
                return " >= 0";
            } elseif (isset($args['blog_id']) && is_numeric($args['blog_id'])) {
                return " = " . $args['blog_id'];
            } elseif (isset($args['include_blogs'])) {
                return " = 0";
            } else {
                $mt = MT::get_instance();
                $ctx = $mt->context();
                $blog = $ctx->stash('blog');
                if ( !empty( $blog ) ) {
                    $tag = is_array($ctx->_tag_stack) ? $ctx->_tag_stack[count($ctx->_tag_stack)-1][0] : null;
                    if ( !empty($tag)
                      && ( $tag === 'mtwebsitepingcount'
                        || $tag === 'mtwebsiteentrycount'
                        || $tag === 'mtwebsitepagecount'
                        || $tag === 'mtwebsitecommentcount' ) )
                    {
                        $website = $blog->is_blog() ? $blog->website() : $blog;
                        if (empty($website))
                            return " = -1";
                        else
                            return " = " . $website->id;
                    } else {
                        return " = " . $blog->id;
                    }
                }
                else
                    return " > 0";
            }
        }
    }

    private function _include_exclude_blogs($name, &$bind, &$args) {
        if (empty($args['include_parent_site']) && empty($args['include_with_website'])) {
            $include_with_website = false;
        } else {
            $include_with_website = true;
        }

        $incl = null;
        $excl = null;
        if (isset($args['include_sites'])
        || isset($args['blog_ids'])
        || isset($args['include_blogs'])
        || isset($args['site_ids'])
        || isset($args['include_websites'])) {
            // The following are aliased
            if (!empty($args['include_sites'])) {
                $incl = $args['include_sites'];
            } elseif (!empty($args['blog_ids'])) {
                $incl = $args['blog_ids'];
            } elseif (!empty($args['include_blogs'])) {
                $incl = $args['include_blogs'];
            } elseif (!empty($args['site_ids'])) {
                $incl = $args['site_ids'];
            } elseif (!empty($args['include_websites'])) {
                $incl = $args['include_websites'];
            }
            $args['include_blogs'] = $incl;
            unset($args['include_sites']);
            unset($args['blog_ids']);
            unset($args['site_ids']);
            unset($args['include_websites']);
        } elseif (isset($args['site_id'])) {
            $incl = $args['site_id'];
        } elseif (isset($args['blog_id'])) {
            $incl = $args['blog_id'];
        }

        if (isset($args['exclude_sites']) || isset($args['exclude_blogs']) || isset($args['exclude_websites'])) {
            $excl = isset($args['exclude_sites']) ? $args['exclude_sites'] : null;
            $excl or $excl = (isset($args['exclude_blogs']) ? $args['exclude_blogs'] : null);
            $excl or $excl = (isset($args['exclude_websites']) ? $args['exclude_websites'] : null);

            if (!isset($args['include_blogs'])) {
                # If only exclude_blogs supplied, set include_blogs as all
                $incl = 'all';
                $args['include_blogs'] = 'all';
            }
        }

        // Compute include_blogs
        if (!empty($incl)) {
            $incl = $this->parse_blog_ids($incl, $include_with_website);
        }
        if (isset($args['allows'])) {
            if (empty($incl)) {
                $incl = $args['allows'];
            } else {
                $incl = array_intersect($incl, $args['allows']);
            }
        }

        // Compute exclude_blogs
        if (!empty($excl)) {
            $excl = $this->parse_blog_ids($excl);
        }

        if (isset($args['denies'])) {
            foreach ($args['denies'] as $val) {
                $denies[$val] = 1;
            }
            if (!empty($excl)) {
                foreach ($excl as $e) {
                    if (!array_key_exists($e, $denies)) {
                        $denies[$e] = 1;
                    }
                }
            }
            $excl = array_keys($denies);
        }

        if (!empty($incl) && !empty($excl)) {
            $incl = array_diff($incl, $excl);
            if (empty($incl)) {
                $mt = MT::get_instance();
                trigger_error($mt->translate(
                    "When the exclude_blogs and include_blogs attributes are used together, the same blog IDs should not be listed as parameters to both of them."
                ));
            } else {
                $incl = array_values($incl);
                $excl = null; // remove all exclude pattern.
            }
        }

        if (!empty($incl)) {
            if (count($incl) > 1) {
                return " in (" . $this->in_ph($name, $bind, $incl) . ' )';
            } else {
                return " = " . $this->ph($name, $bind, array_shift($incl));
            }
        } elseif (!empty($excl)) {
            return " not in (" . $this->in_ph($name, $bind, $excl) . ' )';
        } else {
            if (isset($args['include_blogs']) && strtolower($args['include_blogs']) == 'all') {
                return " >= 0";
            } elseif (isset($args['blog_id']) && is_numeric($args['blog_id'])) {
                return " = " . $this->ph($name, $bind, $args['blog_id']);
            } elseif (isset($args['include_blogs'])) {
                return " = 0";
            } else {
                $mt = MT::get_instance();
                $ctx = $mt->context();
                $blog = $ctx->stash('blog');
                if (!empty($blog)) {
                    $tag = is_array($ctx->_tag_stack) ? $ctx->_tag_stack[count($ctx->_tag_stack)-1][0] : null;
                    if (!empty($tag)
                    && ($tag === 'mtwebsitepingcount'
                        || $tag === 'mtwebsiteentrycount'
                        || $tag === 'mtwebsitepagecount'
                        || $tag === 'mtwebsitecommentcount')) {
                        $website = $blog->is_blog() ? $blog->website() : $blog;
                        if (empty($website)) {
                            return " = -1";
                        } else {
                            return " = " . $this->ph($name, $bind, $website->id);
                        }
                    } else {
                        return " = " . $this->ph($name, $bind, $blog->id);
                    }
                } else {
                    return " > 0";
                }
            }
        }
    }

    public function db2ts($dbts) {
        $dbts = preg_replace('/[^0-9]/', '', $dbts ?? '');
        return $dbts;
    }

    public function ts2db($ts) {
        preg_match('/^(\d\d\d\d)?(\d\d)?(\d\d)?(\d\d)?(\d\d)?(\d\d)?$/', $ts, $matches);
        list($all, $y, $mo, $d, $h, $m, $s) = $matches;
        return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $y, $mo, $d, $h, $m, $s);
    }

    public function apply_extract_date($part, $column) {
        return "extract($part from $column)";
    }

    // Public method
    public function resolve_url($path, $blog_id, $build_type = 3) {
        $path = preg_replace('!/$!', '', $path);
        $blog_id = intval($blog_id);
        # resolve for $path -- one of:
        #      /path/to/file.html
        #      /path/to/index.html
        #      /path/to/
        #      /path/to

        $mt = MT::get_instance();
        $index = $this->escape($mt->config('IndexBasename'));

        require_once('class.mt_fileinfo.php');
        $records = null;
        $extras['join'] = array(
            'mt_template' => array(
                'condition' => "fileinfo_template_id = template_id"
            ),
            'mt_templatemap' => array(
                'condition' => "fileinfo_templatemap_id = templatemap_id",
                'type' => 'left'
            ),
        );

        foreach ( array($path, urldecode($path), urlencode($path)) as $p ) {
            $cond = [];
            $bind = [];
            $cond[] = 'fileinfo_blog_id = '. $this->ph('fileinfo_blog_id', $bind, $blog_id);
            $cond[] = sprintf(
                '((fileinfo_url = %s or fileinfo_url = %s) or (fileinfo_url like %s))',
                $this->ph('fileinfo_url1', $bind, $p),
                $this->ph('fileinfo_url2', $bind, $p. '/'),
                $this->ph('fileinfo_url3', $bind, $p. '/'. $index. '%')
            );
            $cond[] = "template_type != 'backup'";
            $where = implode(' and ', $cond). ' order by length(fileinfo_url) asc';
            $fileinfo= new FileInfo;
            $records = $fileinfo->Find($where, $bind, false, $extras);
            if (!empty($records))
                break;
        }
        $path = $p;
        if (empty($records)) return null;

        $found = false;
        foreach ($records as $record) {
            if ( !empty( $build_type ) ) {
                if ( !is_array( $build_type ) ) {
                    $build_type_array = array( $build_type );
                } else {
                    $build_type_array = $build_type;
                }

                $tmpl =  $record->template();
                $map = $record->templatemap();
                $type = empty( $map ) ? $tmpl->build_type : $map->build_type;

                if ( !in_array( $type, $build_type_array ) ) {
                    continue;
                }
            }

            $fiurl = $record->url;
            if ($fiurl == $path) {
                $found = true;
                break;
            }
            if ($fiurl == "$path/") {
                $found = true;
                break;
            }
            $ext = $record->blog()->file_extension;
            if (!empty($ext)) $ext = '.' . $ext;
            if ($fiurl == ($path.'/'.$index.$ext)) {
                $found = true; break;
            }
            if ($found) break;
        }

        if (!$found) return null;
        $blog = $record->blog();
        $this->_blog_id_cache[$blog->id] =& $blog;
        return $record;
    }

    public function load_index_template($ctx, $tmpl, $blog_id = null) {
        return $this->load_special_template($ctx, $tmpl, 'index', $blog_id);
    }

    public function fetch_websites($args) {
        $args['class'] = 'website';
        return $this->fetch_blogs($args);
    }

    public function fetch_blogs($args = null) {
        $bind = [];

        if (!isset($args['class']))
            $args['class'] = 'blog';

        $cond[] = 'blog_id '. $this->_include_exclude_blogs('blog_id', $bind, $args);
        if ($args['class'] !== '*') {
            $cond[] = "blog_class = ". $this->ph('blog_class', $bind, $args['class']);
        }

        require_once('class.mt_blog.php');
        $blogs = null;
        $blog = new Blog();
        $blogs = $blog->Find(implode(' and ', $cond). ' order by blog_name', $bind);
        return $blogs;
    }

    public function fetch_templates($args = null) {
        $cond = [];

        if (isset($args['type'])) {
            $cond[] = 'template_type = '. $this->ph('template_type', $bind, $args['type']);
        }
        if (isset($args['blog_id'])) {
            $cond[] = 'template_blog_id = '. $this->ph('template_blog_id', $bind, intval($args['blog_id']));
        }

        $where = implode(' and ', $cond). ' order by template_name';

        require_once('class.mt_template.php');
        $template = new Template;
        $result = $template->Find($where, $bind);
        return $result;
    }

    public function fetch_templatemap($args = null) {

        $cond = [];

        if (isset($args['type'])) {
            $cond[] = 'templatemap_archive_type = '. $this->ph('templatemap_archive_type', $bind, $args['type']);
        }
        if (isset($args['blog_id'])) {
            $cond[] = 'templatemap_blog_id = '. $this->ph('templatemap_blog_id', $bind, intval($args['blog_id']));
        }
        if (isset($args['preferred'])) {
            $cond[] = 'templatemap_is_preferred = '.
                                            $this->ph('templatemap_is_preferred', $bind, intval($args['preferred']));
        }
        if (isset($args['build_type'])) {
            $cond[] = 'templatemap_build_type = '.
                                            $this->ph('templatemap_build_type', $bind, intval($args['build_type']));
        }
        if (isset($args['content_type'])) {
            $params = array('content_type' => $args['content_type']);
            if (isset($args['blog_id'])) {
                $params['blog_id'] = $args['blog_id'];
            }
            $content_types = $this->fetch_content_types($params);
            
            if (isset($content_types)) {
                $content_type = $content_types[0];
                $extras['join'] = [
                    'mt_template' => [
                        'condition' => implode(' and ', [
                            "template_id = templatemap_template_id",
                            'template_content_type_id = '.
                                    $this->ph('template_content_type_id', $bind_join, intval($content_type->id)),
                        ]),
                        'bind' => $bind_join,
                    ]
                ];
            }
            else {
                return '';
            }
        }
        if (isset($args['content_type_id'])) {
            if (is_array($args['content_type_id'])) {
                $content_type_id = $args['content_type_id'][0];
            } else {
                $content_type_id = $args['content_type_id'];
            }
            $extras['join'] = [
                'mt_template' => [
                    'condition' => implode(' and ', [
                        "template_id = templatemap_template_id",
                        'template_content_type_id = '.
                                $this->ph('template_content_type_id', $bind_join, intval($content_type_id))
                    ]),
                    'bind' => $bind_join,
                ]
            ];
        }

        $where = implode(' and ', $cond). ' order by templatemap_archive_type';

        require_once('class.mt_templatemap.php');
        $tmap = new TemplateMap;
        $result = $tmap->Find($where, $bind, false, isset($extras) ? $extras : null);
        return $result;
    }

    public function fetch_widgetset($ctx, $name, $blog_ids) {
        $blog_ids = is_array($blog_ids) ? $blog_ids : array($blog_ids);
        $cond = [];
        $cond[] = 'template_blog_id IN ('. $this->in_ph('template_blog_id', $bind, $blog_ids). ')';
        $cond[] = 'template_name = '. $this->ph('template_name', $bind, $name);
        $cond[] = 'template_type = '. $this->ph('template_type', $bind, 'widgetset');

        require_once('class.mt_template.php');
        $template = new Template;
        $res = $template->Load(implode(' and ', $cond). ' order by template_blog_id desc', $bind);
        if (empty($res)) return null;
        return $template;
    }

    public function fetch_widgets_by_id($ctx, $ids) {
        $ids = is_array($ids) ? $ids : array($ids);
        $bind_values = [];
        $placeholders = $this->in_ph('template_id', $bind_values, $ids);
        $where = "template_id IN ($placeholders)";

        require_once('class.mt_template.php');
        $template = new Template;
        $result = $template->Find($where, $bind_values);
        return $result;
    }

    public function fetch_widgets_by_name($ctx, $name, $blog_id) {
        $cond = [];
        $cond[] = 'template_blog_id IN ('. $this->in_ph('template_blog_id', $bind, [$blog_id, 0]). ')';
        $cond[] = 'template_name = '. $this->ph('template_name', $bind, $name);
        $cond[] = 'template_type = '. $this->ph('template_type', $bind, 'widget');

        require_once('class.mt_template.php');
        $template = new Template;
        $result = $template->Find(implode(' and ', $cond). ' order by template_blog_id asc', $bind);
        return $result;
    }

    public function load_special_template($ctx, $tmpl, $type, $blog_id = null) {
        if (empty($blog_id))
            $blog_id = $ctx->stash('blog_id');

        $cond = [];
        $cond[] = 'template_blog_id = '. $this->ph('template_blog_id', $bind, $blog_id);

        if (!empty($tmpl)) {
            $cond[] = sprintf(
                '(template_name = %s or template_outfile = %s or template_identifier = %s)',
                $this->ph('template_name', $bind, $tmpl),
                $this->ph('template_outfile', $bind, $tmpl),
                $this->ph('template_identifier', $bind, $tmpl)
            );
        }
        $cond[] = 'template_type = '. $this->ph('template_type', $bind, $type);

        require_once('class.mt_template.php');
        $template = new Template;
        $template->Load(implode(' and ', $cond), $bind);
        return $template;
    }

    public function fetch_config() {
        require_once('class.mt_config.php');
        $config = new Config;
        $config->Load();
        return $config;
    }

    public function category_link($cid, $at = 'Category', $content_type_id = 0) {
        $cache_key = implode(':', array($cid, $at, $content_type_id));
        if (isset($this->_cat_link_cache[$cache_key])) {
            $url = $this->_cat_link_cache[$cache_key];
        } else {
            if ($at == 'ContentType-Category' && !$content_type_id) {
                return null;
            }
            
            $cond = [];
            $cond[] = 'fileinfo_category_id = '. $this->ph('fileinfo_category_id', $bind, $cid);
            $cond[] = 'fileinfo_archive_type = '. $this->ph('fileinfo_archive_type', $bind, $at);
            require_once('class.mt_fileinfo.php');
            $finfo = new FileInfo;
            $finfos = $finfo->Find(implode(' and ', $cond), $bind);
            if (empty($finfos))
                return null;

            $found = false;
            foreach($finfos as $fi) {
                $tmap = $fi->TemplateMap();
                if ($tmap->is_preferred != 1) {
                    continue;
                }
                if ($at == 'ContentType-Category') {
                    $tmpl = $tmap->template();
                    if ($tmpl->content_type_id != $content_type_id) {
                        continue;
                    }
                }
                $found = true;
                $finfo = $fi;
                break;
            }
            if (!$found)
                return null;

            $blog = $finfo->Blog();
            $blog_url = $blog->archive_url();
            if (empty($blog_url))
                $blog_url = $blog->site_url();
            $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
            $url = $blog_url . $finfo->url;
            require_once('MTUtil.php');
            $url = _strip_index($url, $blog);
            $this->_cat_link_cache[$cache_key] = $url;
        }
        return $url;
    }

    public function archive_link($ts, $at, $args) {
        $blog_id = intval($args['blog_id']);
        if (isset($this->_archive_link_cache[$blog_id.';'.$ts.';'.$at])) {
            $url = $this->_archive_link_cache[$blog_id.';'.$ts.';'.$at];
        } else {

            $ar = ArchiverFactory::get_archiver($at);
            $bind = [];
            $sql = $ar->get_archive_link_sql($ts, $at, $args, $bind);

            if (empty($sql)) {
                $sql = sprintf(
                    "fileinfo_startdate = %s
                            and fileinfo_blog_id = %s
                            and fileinfo_archive_type = %s and templatemap_is_preferred = 1",
                    $this->ph('fileinfo_startdate', $bind, $ts),
                    $this->ph('fileinfo_blog_id', $bind, $blog_id),
                    $this->ph('fileinfo_archive_type', $bind, $at)
                );
            }
            $extras['join'] = array(
                'mt_templatemap' => array(
                    'condition' => "templatemap_id = fileinfo_templatemap_id"
                    )
                );
            
            require_once('class.mt_fileinfo.php');
            $finfo = new FileInfo;
            $infos = $finfo->Find($sql, $bind, false, $extras);
            if (empty($infos))
                return null;

            $finfo = $infos[0];
            $blog = $finfo->blog();
            if ($at == 'Page') {
                $blog_url = $blog->site_url();
            } else {
                $blog_url = $blog->archive_url();
            }

            require_once('MTUtil.php');
            $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
            if (substr($blog_url, -1, 1) == '/' && substr($finfo->fileinfo_url, 0, 1) == '/') {
                $blog_url = substr_replace($blog_url, "", -1);
            }
            $url = $blog_url . $finfo->fileinfo_url;
            $url = _strip_index($url, $blog);
            $this->_archive_link_cache[$ts.';'.$at] = $url;
        }
        return $url;
    }

    public function entry_link($eid, $at = "Individual", $args = null) {
        $eid = intval($eid);
        if (isset($this->_entry_link_cache[$eid.';'.$at])) {
            $url = $this->_entry_link_cache[$eid.';'.$at];
        } else {
            $extras['join'] = array(
                'mt_templatemap' => array(
                    'condition' => "templatemap_id = fileinfo_templatemap_id"
                    )
                );
            $cond = [];
            $cond[] = 'templatemap_archive_type = '. $this->ph('templatemap_archive_type', $bind, $at);
            $cond[] = 'templatemap_is_preferred = 1';

            if (isset($args['blog_id'])) {
                $cond[] = 'fileinfo_blog_id = '. $this->ph('fileinfo_blog_id', $bind, $args['blog_id']);
            }

            if (preg_match('/Category/', $at)) {
                $extras['join']['mt_placement'] = array(
                    'condition' => "fileinfo_category_id = placement_category_id"
                );
                $cond[] = 'placement_entry_id = '. $this->ph('placement_entry_id', $bind, $eid);
                $cond[] = 'placement_is_primary = 1';
            }

            if (preg_match('/Page/', $at)) {
                $entry = $this->fetch_page($eid);
            } else {
                $entry = $this->fetch_entry($eid);
            }

            $ts = $this->db2ts($entry->entry_authored_on);
            if ($ts === '') {
                // DO NOTHING
            } elseif (preg_match('/Monthly$/', $at)) {
                $ts = substr($ts, 0, 6) . '01000000';
            } elseif (preg_match('/Daily$/', $at)) {
                $ts = substr($ts, 0, 8) . '000000';
            } elseif (preg_match('/Weekly$/', $at)) {
                require_once("MTUtil.php");
                list($ws, $we) = start_end_week($ts);
                $ts = $ws;
            } elseif (preg_match('/Yearly$/', $at)) {
                $ts = substr($ts, 0, 4) . '0101000000';
            } elseif ($at == 'Individual' || $at == 'Page') {
                $cond[] = 'fileinfo_entry_id = '. $this->ph('fileinfo_entry_id', $bind, $eid);
            }
            if (preg_match('/(Monthly|Daily|Weekly|Yearly)$/', $at)) {
                $cond[] = 'fileinfo_startdate = '. $this->ph('fileinfo_startdate', $bind, $ts);
            }
            if (preg_match('/Author/', $at)) {
                $cond[] = 'fileinfo_author_id = '. $this->ph('fileinfo_author_id', $bind, $entry->entry_author_id);
            }

            require_once('class.mt_fileinfo.php');
            $finfo = new FileInfo;
            $infos = $finfo->Find(implode(' and ', $cond), $bind, false, $extras);
            if (empty($infos))
                return null;

            $finfo = $infos[0];
            $blog = $finfo->blog();
            if ($at == 'Page') {
                $blog_url = $blog->site_url();
            } else {
                $blog_url = $blog->archive_url();
                if (empty($blog_url))
                    $blog_url = $blog->site_url();
            }

            require_once('MTUtil.php');
            $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
            $url = $blog_url . $finfo->fileinfo_url;
            $url = _strip_index($url, $blog);
            $this->_entry_link_cache[$eid.';'.$at] = $url;
        }

        if ($at != 'Individual' && $at != 'Page') {
            if (!$args || !isset($args['no_anchor'])) {
                $url .= '#' . (!$args || isset($args['valid_html']) ? 'a' : '') .
                        sprintf("%06d", $eid);
            }
        }

        return $url;
    }

    public function get_template_text($ctx, $module, $blog_id = null, $type = 'custom', $global = null) {
        if (!isset($blog_id))
            $blog_id = $ctx->stash('blog_id');

        $bind = [];
        $cond = [];

        if (!isset($global)) {
            $ph = $this->in_ph('template_blog_id', $bind, [$blog_id, 0]);
            $cond[] = "template_blog_id in ($ph)";
        } elseif ($global) {
            $cond[] = "template_blog_id = 0";
        } else {
            $cond[] = "template_blog_id = ". $this->ph('template_blog_id', $bind, $blog_id);
        }

        if ($type === 'custom' || $type === 'widget'|| $type === 'widgetset') {
            $col = 'template_name';
            $cond[] = "$col = ". $this->ph($col, $bind, $module);
            $cond[] = 'template_type = '. $this->ph('template_type', $bind, $type);
        } else {
            $col = 'template_identifier';
            $cond[] = "$col = ". $this->ph($col, $bind, $module);
        }

        require_once('class.mt_template.php');
        $template = new Template;
        $tmpls = $template->Find(implode(' and ', $cond). ' order by template_blog_id desc', $bind);
        if (empty($tmpls)) return '';

        $tmpl = $tmpls[0]->text;
        $ts = $tmpls[0]->modified_on;
        $file = $tmpls[0]->linked_file;
        $mtime = $tmpls[0]->linked_file_mtime;
        $size = $tmpls[0]->linked_file_size;

        if ($file) {
            if (!file_exists($file)) {
                $blog = $ctx->stash('blog');
                if ($blog->id != $blog_id) {
                    $blog = $this->fetch_blog($blog_id);
                }
                $path = $blog->site_path();
                if (!preg_match('![\\/]$!', $path))
                    $path .= '/';
                $path .= $file;
                if (is_file($path) && is_readable($path))
                    $file = $path;
                else
                    $file = '';
            }
            if ($file) {
                if ((filemtime($file) > $mtime) || (filesize($file) != $size)) {
                    $contents = @file($file);
                    $tmpl = implode('', $contents);
                }
            }
        }
        return $tmpl;
    }

    public function fetch_website($blog_id) {
        if (!empty($this->_blog_id_cache) && isset($this->_blog_id_cache[$blog_id])) {
            return $this->_blog_id_cache[$blog_id];
        }
        require_once('class.mt_website.php');
        $blog = new Website;
        $blog->LoadByIntId($blog_id);
        $this->_blog_id_cache[$blog_id] = $blog;
        return $blog;
    }

    public function fetch_blog($blog_id) {
        if (!empty($this->_blog_id_cache) && isset($this->_blog_id_cache[$blog_id])) {
            return $this->_blog_id_cache[$blog_id];
        }
        require_once('class.mt_blog.php');
        $blog = new Blog;
        $blog->LoadByIntId($blog_id);
        $this->_blog_id_cache[$blog_id] = $blog;
        return $blog;
    }

    function fetch_pages($args) {
        $args['class'] = 'page';
        return $this->fetch_entries($args);
    }

    function fetch_entry($eid) {
        if ( isset( $this->_entry_id_cache[$eid] ) && !empty( $this->_entry_id_cache[$eid] ) ) {
            return $this->_entry_id_cache[$eid];
        }
        require_once("class.mt_entry.php");
        $entry = New Entry;
        $entry->LoadByIntId($eid);
        if ( !empty( $entry ) ) {
            $this->_entry_id_cache[$eid] = $entry;
            return $entry;
        } else {
            return null;
        }
    }

    public function fetch_entries($args, &$total_count = NULL) {
        require_once('class.mt_entry.php');
        $extras = array();

        $blog_filter = 'and entry_blog_id ' . $this->_include_exclude_blogs('entry_blog_id', $bind_blog_filter, $args);
        $mt = MT::get_instance();
        $ctx = $mt->context();
        $blog = $ctx->stash('blog');
        if (!empty($blog)) {
            $blog_id = $blog->blog_id;
        }

        if (empty($blog))
            return null;

        // determine any custom fields that we should filter on
        $fields = array();
        foreach ($args as $name => $v)
            if (preg_match('/^field___(\w+)$/', $name, $m))
                $fields[$m[1]] = $v;

        # automatically include offset if in request
        if (isset($args['offset']) && $args['offset'] == 'auto') {
            $args['offset'] = 0;
            if ($args['limit'] || $args['lastn']) {
                if (intval($_REQUEST['offset']) > 0) {
                    $args['offset'] = intval($_REQUEST['offset']);
                }
            }
        }

        if (isset($args['limit']) && $args['limit'] > 0) {
            $args['lastn'] = $args['limit'];
        } elseif (!isset($args['days']) && !isset($args['lastn'])) {
#            if ($days = $blog['blog_days_on_index']) {
#                if (!isset($args['recently_commented_on'])) {
#                    $args['days'] = $days;
#                }
#            } elseif ($posts = $blog['blog_entries_on_index']) {
#                $args['lastn'] = $posts;
#            }
        }
        if (isset($args['limit']) && $args['limit'] == 'auto') {
            if ((intval($_REQUEST['limit']) > 0) && (intval($_REQUEST['limit']) < $args['lastn'])) {
                $args['lastn'] = intval($_REQUEST['limit']);
            } elseif (!isset($args['days']) && !isset($args['lastn'])) {
               if ($days = $blog->blog_days_on_index) {
                    if (!isset($args['recently_commented_on'])) {
                        $args['days'] = $days;
                    }
                } elseif ($posts = $blog->blog_entries_on_index) {
                    $args['lastn'] = $posts;
                }
            }
        }

        if (isset($args['include_blogs']) or isset($args['exclude_blogs'])) {
            $blog_ctx_arg = isset($args['include_blogs']) ?
                array('include_blogs' => $args['include_blogs']) :
                array('exclude_blogs' => $args['exclude_blogs']);
            $include_with_website = !empty($args['include_parent_site']) 
                ? $args['include_parent_site'] : (!empty($args['include_with_website']) ? $args['include_with_website'] : null);
            if (isset($args['include_blogs']) && isset($include_with_website)) {
                $blog_ctx_arg = array_merge($blog_ctx_arg, array('include_with_website' => $include_with_website));
            }
        }

        # a context hash for filter routines
        $ctx = array();
        $filters = array();

        if (!isset($_REQUEST['entry_ids_published'])) {
            $_REQUEST['entry_ids_published'] = array();
        }

        if (isset($args['unique']) && $args['unique']) {
            $filters[] = function($e, $ctx) {
                return !isset($ctx["entry_ids_published"][$e->entry_id]);
            };
            $ctx['entry_ids_published'] = &$_REQUEST['entry_ids_published'];
        }

        # special case for selecting a particular entry
        if (isset($args['entry_id'])) {
            $entry_filter = 'and entry_id = '. $this->ph('entry_id', $bind_entry_filter, $args['entry_id']);
            $start = ''; $end = ''; $limit = 1; $blog_filter = ''; $day_filter = '';
            $bind_blog_filter = [];
        } else {
            $entry_filter = '';
            $bind_entry_filter = [];
        }

        # special case for selecting some particular entries
        if (isset($args['entry_ids'])) {
            $entry_ids = preg_grep('/\A\d+\z/', $args['entry_ids']);
            $ph = $this->in_ph('entry_id', $bind_entry_filter, $entry_ids);
            $entry_filter .= ' and entry_id IN ('. $ph. ')';
        }

        # special case for excluding a particular entry
        if (isset($args['not_entry_id'])) {
            $entry_filter .= ' and entry_id != '. $this->ph('not_entry_id', $bind_entry_filter, $args['not_entry_id']);
        }

        $entry_list = array();

        # Adds a category filter to the filters list.
        $cat_class = 'category';
        if (!isset($args['class']))
            $args['class'] = 'entry';
        if ($args['class'] == 'page')
            $cat_class = 'folder';

        if (isset($args['category']) or isset($args['categories'])) {
            $category_arg = isset($args['category']) ? $args['category'] : $args['categories'];
            require_once("MTUtil.php");
            if (!preg_match('/\b(AND|OR|NOT)\b|\(|\)/i', $category_arg)) {
                $not_clause = false;
                $cats = cat_path_to_category($category_arg, isset($blog_ctx_arg) ? $blog_ctx_arg : null, $cat_class);
                if (empty($cats)) {
                    return null;
                } else {
                    $category_arg = '';
                    foreach ($cats as $cat) {
                        if ($category_arg != '')
                            $category_arg .= ' OR ';
                        $category_arg .= '#' . $cat->category_id;
                    }
                }
            } else {
                $not_clause = preg_match('/\bNOT\b/i', $category_arg);
                if (!empty($blog_ctx_arg))
                    $cats = $this->fetch_categories(array_merge($blog_ctx_arg, array('show_empty' => 1, 'class' => $cat_class)));
                else
                    $cats = $this->fetch_categories(array('blog_id' => $blog_id, 'show_empty' => 1, 'class' => $cat_class));
            }

           if (!empty($cats)) {
               $cexpr = create_cat_expr_function($category_arg, $cats, 'entry', array(
                   'children' => (isset($args['include_subcategories']) ? $args['include_subcategories'] : null)
               ));
               if ($cexpr) {
                   $cmap = array();
                   $cat_list = array();
                   foreach ($cats as $cat)
                       $cat_list[] = $cat->category_id;
                   $pl = $this->fetch_placements(array('category_id' => $cat_list));
                   if (!empty($pl)) {
                       foreach ($pl as $p) {
                           $eid = $p->placement_entry_id;
                           if (!isset($cmap[$eid][$p->placement_category_id])) {
                               $cmap[$eid][$p->placement_category_id] = 0;
                           }
                           $cmap[$eid][$p->placement_category_id]++;
                           if (!$not_clause)
                               $entry_list[$eid] = 1;
                       }
                   }
                   $ctx['c'] =& $cmap;
                   $filters[] = $cexpr;
               } else {
                   return null;
               }
           }
        } elseif (isset($args['category_id'])) {
            require_once("MTUtil.php");
            $cat = $this->fetch_category($args['category_id']);
            if (!empty($cat)) {
                $cats = array($cat);
                $cmap = array();
                $cexpr = create_cat_expr_function($cat->category_label, $cats, 'entry', array(
                    'children' => (isset($args['include_subcategories']) ? $args['include_subcategories'] : null)
                ));
                $pl = $this->fetch_placements(array('category_id' => array($cat->category_id)));
                if (!empty($pl)) {
                    foreach ($pl as $p) {
                        $eid = $p->placement_entry_id;
                        if (!isset($cmap[$eid][$p->placement_category_id])) {
                            $cmap[$eid][$p->placement_category_id] = 0;
                        }
                        $cmap[$eid][$p->placement_category_id]++;
                    }
                    $ctx['c'] =& $cmap;
                    $filters[] = $cexpr;
                } else {
                    # this category have no entries (or pages)
                    return null;
                }
            } else {
                # this category have no entries (or pages)
                return null;
            }
        }
        if ((0 == count($filters)) && (isset($args['show_empty']) && (1 == $args['show_empty']))) {
            return null;
        }

        # Adds a tag filter to the filters list.
        if (isset($args['tags']) or isset($args['tag'])) {
            $tag_arg = isset($args['tag']) ? $args['tag'] : $args['tags'];
            require_once("MTUtil.php");
            $not_clause = preg_match('/\bNOT\b/i', $tag_arg);

            $include_private = 0;
            $tag_array = tag_split($tag_arg);
            foreach ($tag_array as $tag) {
                $tag_body = trim(preg_replace('/\bNOT\b/i','',$tag));
                if ($tag_body && (substr($tag_body,0,1) == '@')) {
                    $include_private = 1;
                }
            }
            if (isset($blog_ctx_arg))
                $tags = $this->fetch_entry_tags(array_merge($blog_ctx_arg, array('tag' => $tag_arg, 'include_private' => $include_private, 'class' => $args['class'])));
            else
                $tags = $this->fetch_entry_tags(array('blog_id' => $blog_id, 'tag' => $tag_arg, 'include_private' => $include_private, 'class' => $args['class']));
            if (!is_array($tags)) $tags = array();
            $cexpr = create_tag_expr_function($tag_arg, $tags);

            if ($cexpr) {
                $tmap = array();
                $tag_list = array();
                foreach ($tags as $tag) {
                    $tag_list[] = $tag->tag_id;
                }
                if (isset($blog_ctx_arg))
                    $ot = $this->fetch_objecttags(array_merge($blog_ctx_arg, array('tag_id' => $tag_list, 'datasource' => 'entry')));
                else
                    $ot = $this->fetch_objecttags(array('tag_id' => $tag_list, 'datasource' => 'entry', 'blog_id' => $blog_id));

                if ($ot) {
                    foreach ($ot as $o) {
                        $oid = $o->objecttag_object_id;
                        if (!isset($tmap[$oid][$o->objecttag_tag_id])) {
                            $tmap[$oid][$o->objecttag_tag_id] = 0;
                        }
                        $tmap[$oid][$o->objecttag_tag_id]++;
                        if (!$not_clause)
                            $entry_list[$oid] = 1;
                    }
                }
                $ctx['t'] =& $tmap;
                $filters[] = $cexpr;
            } else {
                return null;
            }
        }

        # Adds a score or rate filter to the filters list.
        if (isset($args['namespace'])) {
            require_once("MTUtil.php");
            $arg_names = array('min_score', 'max_score', 'min_rate', 'max_rate', 'min_count', 'max_count' );
            foreach ($arg_names as $n) {
                if (isset($args[$n])) {
                    $rating_args = $args[$n];
                    $cexpr = create_rating_expr_function($rating_args, $n, $args['namespace']);
                    if ($cexpr) {
                        $filters[] = $cexpr;
                    } else {
                        return null;
                    }
                }
            }

            if (isset($args['scored_by'])) {
                $voter = $this->fetch_author_by_name($args['scored_by']);
                if (empty($voter)) {
                    echo "Invalid scored by filter: ".$args['scored_by'];
                    return null;
                }
                $cexpr = create_rating_expr_function($voter->author_id, 'scored_by', $args['namespace']);
                if ($cexpr) {
                    $filters[] = $cexpr;
                } else {
                    return null;
                }
            }
        }

        # Adds an count of comment filter
        if (isset($args['max_comment']) && is_numeric($args['max_comment'])) {
            $max_comment_filter = 'and entry_comment_count <= '.
                            $this->ph('entry_comment_count', $bind_max_comment_filter, intval($args['max_comment']));
        }
        if (isset($args['min_comment']) && is_numeric($args['min_comment'])) {
            $min_comment_filter = 'and entry_comment_count >= '.
                            $this->ph('entry_comment_count', $bind_min_comment_filter, intval($args['min_comment']));
        }

        # set a reasonable cap on the entry list cache. if
        # user is selecting something too big, then they'll
        # just have to wait through a scan.
        if (!empty($entry_list) && count($entry_list) < 1024 && ($entry_filter == '')) {
            $bind_entry_filter = [];
            $ph = $this->in_ph('entry_id', $bind_entry_filter, array_keys($entry_list));
            $entry_filter = "and entry_id in ($ph)";
        }

        if (isset($args['author'])) {
            $author_filter = 'and author_name = '. $this->ph('author_name', $bind_author_filter, $args['author']);
            $extras['join']['mt_author'] = array(
                    'condition' => "entry_author_id = author_id"
                    );
        } elseif (isset($args['author_id']) && preg_match('/^\d+$/', $args['author_id']) && $args['author_id'] > 0) {
            $author_filter = "and entry_author_id = ".
                                            $this->ph('entry_author_id', $bind_author_filter, $args['author_id']);
        }

        $date_filter = '';
        if (isset($args['current_timestamp']) || isset($args['current_timestamp_end'])) {
            $timestamp_field = ($args['class'] == 'page') ? 'entry_modified_on' : 'entry_authored_on';
            $date_filter = $this->build_date_filter($args, $timestamp_field);
        }

        if (isset($args['days']) && !$date_filter) {
            $day_filter = 'and ' . $this->limit_by_day_sql('entry_authored_on', intval($args['days']));
        } elseif (isset($args['lastn'])) {
            if (!isset($args['entry_id'])) $limit = $args['lastn'];
        } else {
            $found_valid_args = 0;
            foreach ( array(
                'lastn', 'days',
                'category', 'categories', 'category_id',
                'tag', 'tags',
                'author',
                'min_score',  'max_score',
                'min_rate',    'max_rate',
                'min_count',  'max_count',
                'min_comment', 'max_comment'
              ) as $valid_key )
            {
                if (array_key_exists($valid_key, $args)) {
                    $found_valid_args = 1;
                    break;
                }
            }
            if ((!isset($args['current_timestamp']) &&
                !isset($args['current_timestamp_end'])) &&
                (empty($limit) || $limit <= 0) &&
                (!$found_valid_args) &&
                (isset($blog))) {
                if ($days = $blog->blog_days_on_index) {
                    if (!isset($args['recently_commented_on'])) {
                        $day_filter = 'and ' . $this->limit_by_day_sql('entry_authored_on', intval($days));
                    }
                } elseif ($posts = $blog->blog_entries_on_index) {
                    $limit = $posts;
                }
            }
        }

        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'ascend') {
                $order = 'asc';
            } elseif ($args['sort_order'] == 'descend') {
                $order = 'desc';
            }
        } 
        if (!isset($order)) {
            $order = 'desc';
            if (isset($blog) && isset($blog->blog_sort_order_posts)) {
                if ($blog->blog_sort_order_posts == 'ascend') {
                    $order = 'asc';
                }
            }
        }

        if (isset($args['class'])) {
            $class = $args['class'];
        } else {
            $class = 'entry';
        }
        if ($args['class'] == '*') {
            $class_filter = '';
        } else {
            $class_filter = "and entry_class=". $this->ph('entry_class', $bind_class_filter, $class);
        }

        if ( isset($args['sort_by'])
            && (($args['sort_by'] == 'score') || ($args['sort_by'] == 'rate'))) {
            $extras['join'] = array(
                'mt_objectscore' => array(
                    'type' => 'left',
                    'condition' => sprintf(
                        "objectscore_object_id = entry_id and objectscore_namespace=%s and objectscore_object_ds=%s",
                        $this->ph('objectscore_namespace', $bind_objectscore, $args['namespace']),
                        $this->ph('objectscore_object_ds', $bind_objectscore, $class)
                    ),
                    'bind' => $bind_objectscore,
                )
            );
            $extras['distinct'] = 1;
        }

        if (isset($args['offset']))
            $offset = $args['offset'];

        if (isset($args['limit']) || isset($args['offset'])) {
            if (isset($args['sort_by'])) {
                if ($args['sort_by'] == 'title') {
                    $sort_field = 'entry_title';
                } elseif ($args['sort_by'] == 'id') {
                    $sort_field = 'entry_id';
                } elseif ($args['sort_by'] == 'status') {
                    $sort_field = 'entry_status';
                } elseif ($args['sort_by'] == 'modified_on') {
                    $sort_field = 'entry_modified_on';
                } elseif ($args['sort_by'] == 'author_id') {
                    $sort_field = 'entry_author_id';
                } elseif ($args['sort_by'] == 'excerpt') {
                    $sort_field = 'entry_excerpt';
                } elseif ($args['sort_by'] == 'comment_created_on') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'trackback_count') {
                    $sort_field = 'entry_ping_count';
                } elseif (preg_match('/field[:\.]/', $args['sort_by'])) {
                    $post_sort_limit = $limit ? $limit : 0;
                    $post_sort_offset = !empty($offset) ? $offset : 0;
                    $limit = 0; $offset = 0;
                    $no_resort = 0;
                } else {
                    if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                        throw new MTDBException('illegal sort_by name');
                    }
                    $sort_field = 'entry_' . $args['sort_by'];
                }
                if (!empty($sort_field)) $no_resort = 1;
                if ($args['sort_by'] == 'score' || $args['sort_by'] == 'rate') {
                    $post_sort_limit = $limit;
                    $post_sort_offset = $offset;
                    $limit = 0; $offset = 0;
                    $no_resort = 0;
                    $sort_field = "entry_modified_on";
                }
            }
            else {
                $sort_field = isset($timestamp_field) ? $timestamp_field : 'entry_authored_on'; 
            }
        } else {
            $sort_field = isset($timestamp_field) ? $timestamp_field : 'entry_authored_on'; 
            $no_resort = 0;
        }

        if (!empty($sort_field)) {
            $base_order = (
                isset( $args['sort_order'] )
                    ? $args['sort_order']
                    : ( isset( $args['base_sort_order'] )
                            ? $args['base_sort_order']
                            : '' )
            ) === 'ascend' ? 'asc' : 'desc';
        }

        if (count($filters) || !is_null($total_count)) {
            $post_select_limit = isset($limit) ? $limit : null;
            $post_select_offset = isset($offset) ? $offset : null;
            $limit = 0; $offset = 0;
        }

        if (count($fields)) {
            $meta_join_num = 1;
            $entry_meta_info = Entry::get_meta_info('entry');
            if (!empty($entry_meta_info)) {
                foreach ($fields as $name => $value) {
                    if (isset($entry_meta_info['field.'.$name])) {
                        $meta_col = $entry_meta_info['field.'.$name];
                        $table = "mt_entry_meta entry_meta$meta_join_num";
                        $extras['join'][$table] = array(
                            'condition' => sprintf(
                                "(entry_meta$meta_join_num.entry_meta_entry_id = entry_id
                                and entry_meta$meta_join_num.entry_meta_type = %s
                                and entry_meta$meta_join_num.entry_meta_$meta_col=%s)\n",
                                $this->ph("entry_meta{$meta_join_num}_1", $bind_entry_meta, "field.$name"),
                                $this->ph("entry_meta{$meta_join_num}_2", $bind_entry_meta, $value)
                            ),
                            'bind' => $bind_entry_meta,
                        );
                        $meta_join_num++;
                    }
                }
            }
        }

        $join_clause = '';
        if (isset($extras['join'])) {
            $joins = $extras['join'];
            $keys = array_keys($joins);
            $bind_join_clause = [];
            foreach($keys as $key) {
                $table = $key;
                $cond = $joins[$key]['condition'];
                $type = '';
                if (isset($joins[$key]['type']))
                    $type = $joins[$key]['type'];
                $join_clause .= ' ' . strtolower($type) . ' JOIN ' . $table . ' ON ' . $cond;
                $bind_join_clause =
                    array_merge($bind_join_clause, isset($joins[$key]['bind']) ? $joins[$key]['bind'] : []);
            }
        }
        
        $bind = array_merge(
            isset($bind_join_clause) ? $bind_join_clause : [],
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_entry_filter) ? $bind_entry_filter : [],
            isset($bind_author_filter) ? $bind_author_filter : [],
            isset($bind_class_filter) ? $bind_class_filter : [],
            isset($bind_max_comment_filter) ? $bind_max_comment_filter : [],
            isset($bind_min_comment_filter) ? $bind_min_comment_filter : []
        );

        $sql = implode(' ', array(
            'select mt_entry.* from mt_entry', $join_clause, 'where', 'entry_status = 2',
            isset($blog_filter) ? $blog_filter : '',
            isset($entry_filter) ? $entry_filter : '',
            isset($author_filter) ? $author_filter : '',
            isset($date_filter) ? $date_filter : '',
            isset($day_filter) ? $day_filter : '',
            isset($class_filter) ? $class_filter : '',
            isset($max_comment_filter) ? $max_comment_filter : '',
            isset($min_comment_filter) ? $min_comment_filter : ''
        ));
        if (!empty($sort_field)) {
            $sql .= " order by $sort_field $base_order";
            if ($sort_field == 'entry_authored_on') {
                $sql .= ", entry_id $base_order";
            }
        }

        if (isset($args['recently_commented_on'])) {
            $rco = $args['recently_commented_on'];
            $sql = $this->entries_recently_commented_on_sql($sql);
            !empty($args['sort_by']) or $args['sort_by'] = 'comment_created_on';
            !empty($args['sort_order']) or $args['sort_order'] = 'descend';
            $post_select_limit = $rco;
            $no_resort = 1;
        } elseif ( !is_null($total_count) ) {
            $orig_offset = $post_select_offset ? $post_select_offset : $offset;
            $orig_limit = $post_select_limit ? $post_select_limit : $limit;
        }

        if (empty($limit) || $limit <= 0) $limit = -1;
        if (empty($offset) || $offset <= 0) $offset = -1;
        $result = $this->SelectLimit($sql, $limit, $offset, $bind);
        if ($result->EOF) return null;

        $field_names = array_keys($result->fields);

        $entries = array();
        $j = 0;
        $offset = isset($post_select_offset) ? $post_select_offset : (isset($orig_offset) ? $orig_offset : null);
        $limit = isset($post_select_limit) ? $post_select_limit : 0;
        $id_list = array();
        $_total_count = 0;
        while (!$result->EOF) {
            $e = new Entry;
            foreach($field_names as $key) {
                if (is_numeric($key)) continue;
  	            $key = strtolower($key);
                $e->$key = $result->fields($key);
            }
            $result->MoveNext();

            if (empty($e)) break;
            if (count($filters)) {
                foreach ($filters as $f) {
                    if (!$f($e, $ctx)) {
                        continue 2;
                    }
                }
            }
            $_total_count++;
            if ( !is_null($total_count) ) {
                if ( ($orig_limit > 0)
                  && ( ($_total_count-$offset) > $orig_limit) ) {
                    // collected all the entries; only count numbers;
                    continue;
                }
            }
            if ($offset && ($j++ < $offset)) continue;
            $e->entry_authored_on = $this->db2ts($e->entry_authored_on);
            $e->entry_modified_on = $this->db2ts($e->entry_modified_on);
            $id_list[] = $e->entry_id;
            $entries[] = $e;
            $this->_comment_count_cache[$e->entry_id] = $e->entry_comment_count;
            $this->_ping_count_cache[$e->entry_id] = $e->entry_ping_count;
            if ( is_null($total_count) ) {
                // the request does not want total count; break early
                if (($limit > 0) && (count($entries) >= $limit)) break;
            }
        }
        Entry::bulk_load_meta($entries);

        if ( !is_null($total_count) )
            $total_count = $_total_count;

        if (empty($no_resort)) {
            $sort_field = '';
            if (isset($args['sort_by'])) {
                if ($args['sort_by'] == 'title') {
                    $sort_field = 'entry_title';
                } elseif ($args['sort_by'] == 'id') {
                    $sort_field = 'entry_id';
                } elseif ($args['sort_by'] == 'status') {
                    $sort_field = 'entry_status';
                } elseif ($args['sort_by'] == 'modified_on') {
                    $sort_field = 'entry_modified_on';
                } elseif ($args['sort_by'] == 'author_id') {
                    $sort_field = 'entry_author_id';
                } elseif ($args['sort_by'] == 'excerpt') {
                    $sort_field = 'entry_excerpt';
                } elseif ($args['sort_by'] == 'comment_created_on') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'score') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'rate') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'trackback_count') {
                    $sort_field = 'entry_ping_count';  
                } elseif (preg_match('/^field[:\.](.+)$/', $args['sort_by'], $match)) {
                    $sort_field = 'entry_field.' . $match[1];
                } else {
                    if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                        throw new MTDBException('illegal sort_by name');
                    }
                    $sort_field = 'entry_' . $args['sort_by'];
                }
            } else {
                $sort_field = 'entry_authored_on';
            }

            if ($sort_field) {
                $bind2 = array_merge(
                    isset($bind_blog_filter) ? $bind_blog_filter : [],
                    isset($bind_entry_filter) ? $bind_entry_filter : [],
                    isset($bind_author_filter) ? $bind_author_filter : [],
                    isset($bind_class_filter) ? $bind_class_filter : []
                );
                if ($sort_field == 'score') {
                    $offset = !empty($post_sort_offset) ? $post_sort_offset : 0;
                    $limit = !empty($post_sort_limit) ? $post_sort_limit : 0;
                    $entries_tmp = array();
                    foreach ($entries as $e) {
                        $entries_tmp[$e->entry_id] = $e;
                    }
                    $scores = $this->fetch_sum_scores($args['namespace'], 'entry', $order,
                        implode(' ', array(
                            isset($blog_filter) ? $blog_filter : '',
                            isset($entry_filter) ? $entry_filter : '',
                            isset($author_filter) ? $author_filter : '',
                            isset($date_filter) ? $date_filter : '',
                            isset($day_filter) ? $day_filter : '',
                            isset($class_filter) ? $class_filter : '',
                        )),
                        $bind2
                    );
                    $entries_sorted = array();
                    foreach($scores as $score) {
                        if (--$offset >= 0) continue;
                        if (array_key_exists($score['objectscore_object_id'], $entries_tmp)) {
                            array_push($entries_sorted, $entries_tmp[$score['objectscore_object_id']]);
                            unset($entries_tmp[$score['objectscore_object_id']]);
                            if (--$limit == 0) break;
                        }
                    }
                    foreach ($entries_tmp as $et) {
                        if ($limit == 0) break;
                        if ($order == 'asc')
                            array_unshift($entries_sorted, $et);
                        else
                            array_push($entries_sorted, $et);
                        $limit--;
                    }
                    $entries = $entries_sorted;
                } elseif ($sort_field == 'rate') {
                    $offset = $post_sort_offset ? $post_sort_offset : 0;
                    $limit = $post_sort_limit ? $post_sort_limit : 0;
                    $entries_tmp = array();
                    foreach ($entries as $e) {
                        $entries_tmp[$e->entry_id] = $e;
                    }
                    $scores = $this->fetch_avg_scores($args['namespace'], 'entry', $order,
                        implode(' ', array(
                            isset($blog_filter) ? $blog_filter : '',
                            isset($entry_filter) ? $entry_filter : '',
                            isset($author_filter) ? $author_filter : '',
                            isset($date_filter) ? $date_filter : '',
                            isset($day_filter) ? $day_filter : '',
                            isset($class_filter) ? $class_filter : ''
                        )),
                        $bind2
                    );
                    $entries_sorted = array();
                    foreach($scores as $score) {
                        if (--$offset >= 0) continue;
                        if (array_key_exists($score->objectscore_object_id, $entries_tmp)) {
                            array_push($entries_sorted, $entries_tmp[$score->objectscore_object_id]);
                            unset($entries_tmp[$score->objectscore_object_id]);
                            if (--$limit == 0) break;
                        }
                    }
                    foreach ($entries_tmp as $et) {
                        if ($limit == 0) break;
                        if ($order == 'asc')
                            array_unshift($entries_sorted, $et);
                        else
                            array_push($entries_sorted, $et);
                        $limit--;
                    }
                    $entries = $entries_sorted;
                } elseif ($sort_field == 'entry_authored_on') {
                    // already double-sorted by the DB
                } else {
                    if (preg_match('/^entry_(field\..*)/', $sort_field, $match)) {
                        if (! isset($entry_meta_info)) {
                            if ($class === '*') {
                                $entry_meta_info = array_merge(
                                    BaseObject::get_meta_info('entry'),
                                    BaseObject::get_meta_info('page')
                                );
                            }
                            else {
                                $entry_meta_info = Entry::get_meta_info($class);
                            }
                        }
                        $sort_by_numeric =
                            preg_match('/integer|float/', $entry_meta_info[$match[1]] ?? '');
                    }
                    else {
                        $sort_by_numeric =
                            ($sort_field == 'entry_status') || ($sort_field == 'entry_author_id') || ($sort_field == 'entry_id')
                            || ($sort_field == 'entry_comment_count') || ($sort_field == 'entry_ping_count');
                    }

                    if ($sort_by_numeric) {
                        $sort_fn = function($a, $b) use ($sort_field) {
                            $f = addslashes($sort_field);
                            if ($a->$f == $b->$f) return 0;
                            return $a->$f < $b->$f ? -1 : 1;
                        };
                    } else {
                        $sort_fn = function($a, $b) use ($sort_field) {
                            $f = addslashes($sort_field);
                            return strcmp($a->$f ?? '', $b->$f ?? '');
                        };
                    }

                    if ($order == 'asc') {
                        $sorter = function($a, $b) use ($sort_fn) {
                            return $sort_fn($a, $b);
                        };
                    } else {
                        $sorter = function($b, $a) use ($sort_fn) {
                            return $sort_fn($a, $b);
                        };
                    }
                    usort($entries, $sorter);

                    if (isset($post_sort_offset)) {
                        $entries = array_slice($entries, $post_sort_offset, $post_sort_limit);
                    }
                }
            }
        }

        if (count($id_list) <= 30) { # TODO: find a good upper limit
            # pre-cache comment counts and categories for these entries
            $this->cache_categories($id_list);
            $this->cache_permalinks($id_list);
        }

        return $entries;
    }

    public function fetch_plugin_config($plugin, $scope = "system") {
        if ($scope != 'system') {
            $key = 'configuration:'.$scope;
        } else {
            $key = 'configuration';
        }
        return $this->fetch_plugin_data($plugin, $key);
    }

    public function fetch_plugin_data($plugin, $key) {

        require_once('class.mt_plugindata.php');
        $class = new PluginData;
        $cond = [];
        $cond[] = 'plugindata_plugin = '. $this->ph('plugindata_plugin', $bind, $plugin);
        $cond[] = 'plugindata_key = '. $this->ph('plugindata_key', $bind, $key);

        $pdatas = $class->Find(implode(' and ', $cond), $bind);
        if (!empty($pdatas)) {

            $data = $pdatas[0]->data;
            if ($data) {
                return $this->unserialize($data);
            }
        }
        return null;
    }

    public function fetch_entry_tags($args) {
        # load tags
        $bind = [];

        $class = isset($args['class']) ? $args['class'] : 'entry';
        $cacheable 
            = empty( $args['tags'] )
            && empty( $args['tag'] )
            && empty( $args['include_private'] );

        if (isset($args['entry_id'])) {
            if ($cacheable) {
                if (isset($this->_entry_tag_cache[$args['entry_id']])) {
                    return $this->_entry_tag_cache[$args['entry_id']];
                }
            }
            $sub_where = 'objecttag_object_id = '. $this->ph('objecttag_object_id', $bind, intval($args['entry_id']));
            $entry_filter = 'and objecttag_tag_id in (select objecttag_tag_id from mt_objecttag where '. $sub_where. ')';
        }

        $blog_filter = 'and objecttag_blog_id ' . $this->_include_exclude_blogs('objecttag_blog_id', $bind, $args);

        if (! empty($args['tags'])) {
            require_once("MTUtil.php");
            $tag_array = tag_split($args['tags']);
            $ph = $this->in_ph('tag_name', $bind, $tag_array);
            $tag_filter = 'and (tag_name in (' . $ph. '))';
        }
        if (empty($tag_filter) && empty($args['include_private'])) {
            $private_filter = 'and (tag_is_private = 0 or tag_is_private is null)';
        }

        $sort_col = isset($args['sort_by']) ? $args['sort_by'] : 'name';
        $sort_col = "tag_$sort_col";
        if (isset($args['sort_order']) and $args['sort_order'] == 'descend') {
            $order = 'desc';
        } else {
            $order = 'asc';
        }
        $id_order = '';
        if ($sort_col == 'tag_name' || $sort_col == 'name') {
            $sort_col = 'lower(tag_name)';
        }else{
            $id_order = ', lower(tag_name)';
        }

        $sql = implode(' ', array(
            'select tag_id, tag_name, count(*) as tag_count',
            'from mt_tag, mt_objecttag, mt_entry',
            'where objecttag_tag_id = tag_id',
            "and entry_id = objecttag_object_id and objecttag_object_datasource='entry'",
            'and entry_status = 2',
            isset($entry_filter) ? $entry_filter : '',
            isset($blog_filter) ? $blog_filter : '',
            isset($tag_filter) ? $tag_filter : '',
            isset($private_filter) ? $private_filter : '',
            "and entry_class = ". $this->ph('entry_class', $bind, $class),
            "group by tag_id, tag_name",
            "order by $sort_col $order $id_order, tag_id desc"
        ));
        $rs = $this->SelectLimit($sql, -1, -1, $bind);

        require_once('class.mt_tag.php');
        $tags = array();
        while(!$rs->EOF) {
            $tag = new Tag;
            $tag->tag_id = $rs->Fields('tag_id');
            $tag->tag_name = $rs->Fields('tag_name');
            $tag->tag_count = $rs->Fields('tag_count');
            $tags[] = $tag;
            $rs->MoveNext();
        }
        if ($cacheable) {
            if (!empty($args['entry_id']))
                $this->_entry_tag_cache[$args['entry_id']] = $tags;
            elseif ($args['blog_id'])
                $this->_blog_tag_cache[$args['blog_id'].":$class"] = $tags;
        }
        return $tags;
    }

    public function fetch_asset_tags($args) {
        $bind = [];
        $cond = [];
        $cond[] = 'objecttag_tag_id = tag_id';
        $cond[] = 'asset_id = objecttag_object_id';
        $cond[] = "objecttag_object_datasource='asset'";

        # load tags by asset
        $cacheable = empty( $args['tags'] )
            && empty( $args['include_private'] );

        if (empty($args['include_private'])) {
            $private_filter = '(tag_is_private = 0 or tag_is_private is null)';
        }

        if (isset($args['asset_id'])) {
            if ($cacheable) {
                if (isset($this->_asset_tag_cache[$args['asset_id']]))
                    return $this->_asset_tag_cache[$args['asset_id']];
            }
            $cond[] = 'objecttag_object_id = '. $this->ph('objecttag_object_id', $bind, intval($args['asset_id']));
        }
        
        if (isset($args['blog_id'])) {
            if ($cacheable) {
                if (isset($this->_blog_asset_tag_cache[$args['blog_id']]))
                    return $this->_blog_asset_tag_cache[$args['blog_id']];
            }
            $cond[] = 'objecttag_blog_id = '. $this->ph('objecttag_blog_id', $bind, intval($args['blog_id']));
        }

        if (! empty($args['tags'])) {
            require_once("MTUtil.php");
            $tag_array = tag_split($args['tags']);
            if (!empty($tag_array)) {
                $cond[] = 'tag_name in (' . $this->in_ph('tag_name', $bind, $tag_array). ')';
                $private_filter = '';
            }
        }

        if (!empty($private_filter)) {
            $cond[] = $private_filter;
        }

        $sort_col = isset($args['sort_by']) ? $args['sort_by'] : 'name';
        $sort_col = "tag_$sort_col";
        if (isset($args['sort_order']) and $args['sort_order'] == 'descend')
            $order = 'desc';
        else
            $order = 'asc';

        $id_order = '';
        if ($sort_col == 'tag_name')
            $sort_col = 'lower(tag_name)';
        else
            $id_order = ', lower(tag_name)';

        $sql = implode(' ', array(
            "select tag_id, tag_name, count(*) as tag_count",
            "from mt_tag, mt_objecttag, mt_asset",
            "where",
            implode(' and ', $cond),
            "group by tag_id, tag_name",
            "order by $sort_col $order $id_order"
        ));
        $rs = $this->SelectLimit($sql, -1, -1, $bind);

        require_once('class.mt_tag.php');
        $tags = array();
        while(!$rs->EOF) {
            $tag = new Tag;
            $tag->tag_id = $rs->Fields('tag_id');
            $tag->tag_name = $rs->Fields('tag_name');
            if (isset($asset_filter)) {
                $tag->tag_count = '';
            } else {
                $tag->tag_count = $rs->Fields('tag_count');
            }
            $tags[] = $tag;
            $rs->MoveNext();
        }

        if ($cacheable) {
            if (!empty($args['asset_id']))
                $this->_asset_tag_cache[$args['asset_id']] = $tags;
            elseif (!empty($args['blog_id']))
                $this->_blog_asset_tag_cache[$args['blog_id']] = $tags;
        }
        return $tags;
    }

    public function fetch_folders($args) {
        $args['class'] = 'folder';
        return $this->fetch_categories($args);
    }

    public function fetch_category($cat_id) {
        if (isset($this->_cat_id_cache['c'.$cat_id])) {
            return $this->_cat_id_cache['c'.$cat_id];
        }
        $cats = $this->fetch_categories(array('category_id' => $cat_id, 'show_empty' => 1));
        if (isset($cats) && (count($cats) > 0)) {
            $this->_cat_id_cache['c'.$cat_id] = $cats[0];
            return $cats[0];
        } else {
            return null;
        }
    }

    public function fetch_categories($args) {
        # load categories
        $bind = [];
        $bind_join = [];
        $blog_filter = 'and category_blog_id '. $this->_include_exclude_blogs('category_blog_id', $bind, $args);
        if (isset($args['parent'])) {
            $parent = $args['parent'];
            if (is_array($parent)) {
                $ph = $this->in_ph('category_parent', $bind, $parent);
                $parent_filter = 'and category_parent in (' . $ph . ')';
            } else {
                $parent_filter = 'and category_parent = '. $this->ph('category_parent', $bind, $parent);
            }
        }
        $cat_filter = '';
        if (isset($args['category_id'])) {
            if (isset($args['children'])) {
                if (isset($this->_cat_id_cache['c'.$args['category_id']])) {
                    $cat = $this->_cat_id_cache['c'.$args['category_id']];
                    $children = $cat->children();
                    if (!empty($children)) {
                        if ($children === false) {
                            return null;
                        } else {
                            return $children;
                        }
                    }
                }

                $cat_filter = 'and category_parent = '. $this->ph('category_parent', $bind, $args['category_id']);
            } else {
                $cat_filter = 'and category_id = '. $this->ph('category_id', $bind, $args['category_id']);
                $limit = 1;
            }
        } elseif (isset($args['label'])) {
            if (is_array($args['label'])) {
                $ph = $this->in_ph('category_label', $bind, $args['label']);
                $cat_filter = 'and category_label in ('. $ph. ')';
            } else {
                $cat_filter = 'and category_label = '. $this->ph('category_label', $bind, $args['label']);
            }
        } else {
            $limit = isset($args['lastn']) ? $args['lastn'] : null;
        }

        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'ascend') {
                $sort_order = 'asc';
            } elseif ($args['sort_order'] == 'descend') {
                $sort_order = 'desc';
            }
        } else {
            $sort_order = '';
        }
        $sort_by = 'user_custom';
        if ( isset($args['sort_by']) ) {
            if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                throw new MTDBException('illegal sort_by name');
            }
            $sort_by = strtolower($args['sort_by']);
            if ( 'user_custom' != $sort_by ) {
                require_once('class.mt_category.php');
                $category_class = new Category();
                if ( $category_class->has_column('category_'.$sort_by) ) {
                    $tableInfo =& $category_class->TableInfo();
                    if ( $tableInfo->flds['category_'.$sort_by]->type == "CLOB" ) {
                        $sort_by = $this->decorate_column('category_'.$sort_by);
                    } else {
                        $sort_by  = 'category_'.$sort_by;
                    }
                } else {
                    $sort_by = 'user_custom';
                }
           }
        }

        if (!isset($args['category_set_id']) || $args['category_set_id'] === 0) {
            if (!empty($args['show_empty'])) {
                $join_clause = 'left outer join mt_placement on placement_category_id = category_id';
                if (isset($args['entry_id'])) {
                    $join_clause .= ' left outer join mt_entry on placement_entry_id = entry_id and entry_id = '.
                                                        $this->ph('entry_id', $bind_join, intval($args['entry_id']));
                } else {
                    $join_clause .= ' left outer join mt_entry on placement_entry_id = entry_id and entry_status = 2';
                }
                $count_column = 'entry_id';
            } else {
                $join_clause = ', mt_entry, mt_placement';
                $cat_filter .= ' and placement_category_id = category_id';
                if (isset($args['entry_id'])) {
                    $entry_filter = ' and placement_entry_id = entry_id and placement_entry_id = '.
                                                            $this->ph('placement_entry_id', $bind, $args['entry_id']);
                } else {
                    $entry_filter = ' and placement_entry_id = entry_id and entry_status = 2';
                }
                $count_column = 'placement_id';
            }
        }
        else {
            if (!empty($args['show_empty'])) {
                $join_clause = 'left outer join mt_objectcategory on objectcategory_category_id = category_id and objectcategory_object_ds = \'content_data\'';
                if (isset($args['content_id'])) {
                    $join_clause .= ' left outer join mt_cd on objectcategory_object_id = cd_id and cd_id = '.
                                                    $this->ph('content_id', $bind_join, intval($args['content_id']));
                } else {
                    $join_clause .= ' left outer join mt_cd on objectcategory_object_id = cd_id and cd_status = 2';
                }
                $count_column = 'cd_id';
            } else {
                $join_clause = ', mt_cd, mt_objectcategory';
                $cat_filter .= ' and objectcategory_category_id = category_id and objectcategory_object_ds = \'content_data\'';
                if (isset($args['content_id'])) {
                    $entry_filter = ' and objectcategory_object_id = cd_id and objectcategory_object_id = '.
                                                    $this->ph('objectcategory_object_id', $bind, $args['content_id']);
                } else {
                    $entry_filter = ' and objectcategory_object_id = cd_id and cd_status = 2';
                }
                if (isset($args['content_type'])) {
                  $mt = MT::get_instance();
                  $ctx = $mt->context();
                  if ($ctx->stash('content_type')){
                    $content_type = $ctx->stash('content_type');
                  } else {
                    $content_types = $ctx->mt->db()->fetch_content_types($args);
                    if ($content_types){
                      $content_type = $content_types[0];
                    }
                  }
                    if (isset($content_type)) {
                        $content_type_filter = ' and cd_content_type_id = '.
                                                            $this->ph('cd_content_type_id', $bind, $content_type->id);
                    }
                }
                $count_column = 'objectcategory_id';
            }
        }

        if (isset($args['class'])) {
            $class = $args['class'];
        } else {
            $class = "category";
        }
        $class_filter = " and category_class = ". $this->ph('category_class', $bind, $class);

        if (isset($args['category_set_id'])) {
            if ($args['category_set_id'] !== '*' && $args['category_set_id'] !== '> 0') {
                $category_set_id = intval($args['category_set_id']);
            }
        } else if (!isset($args['category_id'])
            && (!isset($args['parent']) || !$args['parent']))
        {
            $category_set_id = 0;
        }
        if (isset($category_set_id)) {
            $category_set_filter = "and category_category_set_id = ".
                                                        $this->ph('category_category_set_id', $bind, $category_set_id);
        }
        elseif (isset($args['category_set_id']) && $args['category_set_id'] === '> 0') {
            $category_set_filter = "and category_category_set_id > 0";
        }

        $sql = implode(' ', array(
            "select category_id, count($count_column) as category_count from mt_category $join_clause where 1 = 1",
            isset($blog_filter) ? $blog_filter : '',
            isset($parent_filter) ? $parent_filter : '',
            isset($cat_filter) ? $cat_filter : '',
            isset($entry_filter) ? $entry_filter : '',
            isset($content_type_filter) ? $content_type_filter : '',
            isset($class_filter) ? $class_filter : '',
            isset($category_set_filter) ? $category_set_filter : '',
            "group by category_id"
        ));

        if (empty($limit) || $limit <= 0) $limit = -1;
        $categories = $this->SelectLimit($sql, $limit, -1, array_merge($bind_join, $bind));
        if ($categories->EOF)
            return null;

        if (isset($args['children']) && isset($parent_cat)) {
            $parent_cat['_children'] =& $categories;
        } else {
            $ids = array();
            $counts = array();
            while (!$categories->EOF) {
                $ids[] = $categories->Fields('category_id');
                $categories->MoveNext();
            }

            require_once('class.mt_category.php');
            $category = new Category;
            $base_sort = 'user_custom' == $sort_by ? 'category_label' : $sort_by;
            $where = 'category_id in ('. $this->in_ph('template_blog_id', $bind_cat, $ids). ')';
            $categories = $category->Find($where. " order by $base_sort $sort_order", $bind_cat);
            if (!$categories) $categories = array();
            if ( count($categories) > 1 && 'user_custom' == $sort_by ) {
                $mt = MT::get_instance();
                try {
                    if (isset($category_set_id) && $category_set_id) {
                        $category_set = $mt->db()->fetch_category_set($category_set_id);
                        if ($category_set) {
                            $custom_order = $category_set->order;
                        }
                    } else {
                        $ctx = $mt->context();
                        $blog = $ctx->stash('blog');
                        $meta = $class.'_order';
                        $custom_order = $blog->$meta;
                    }
                    if ( !empty($custom_order) ) {
                        $order_list = preg_split('/\s*,\s*/', $custom_order);
                        $cats = array();
                        foreach ( $categories as $c ) {
                            if ( in_array( $c->id, $order_list ) ) {
                                $key = array_search( $c->id, $order_list );
                                $cats[ $key ] = $c;
                            } else {
                                array_push( $cats, $c );
                            }
                        }
                        if ( 'desc' == $sort_order ) {
                            krsort( $cats );
                        } else {
                            ksort( $cats );
                        }
                        $categories = array_values($cats);
                    }
                } catch (Exception $e) {
                }
            } else {
                
            }

            $id_list = array();
            $top_cats = array();
            $record_count = count($categories);
            for ($i = 0; $i < $record_count; $i++) {
                $cat = $categories[$i];
                $cat_id = $cat->category_id;
                if (isset($args['top_level_categories']) || !isset($this->_cat_id_cache['c'.$cat_id])) {
                    $id_list[] = $cat_id;
                    $this->_cat_id_cache['c'.$cat_id] = $categories[$i];
                }
                if (isset($args['top_level_categories'])) {
                    $this->_cat_id_cache['c'.$cat_id]->children(false);
                }

                if ($cat->category_parent > 0) {
                    $parent_id = $cat->category_parent;
                    if (isset($this->_cat_id_cache['c'.$parent_id])) {
                        if (isset($args['top_level_categories'])) {
                            $parent = $this->fetch_category($parent_id);
                            $children = $parent->children();
                            if (empty($children) || ($children === false)) {
                                $parent->children(array(&$categories[$i]));
                            } else {
                                $parent->children($categories[$i]);
                            }
                        }
                    }
                }
                if ((!$cat->category_parent) && (isset($args['top_level_categories']))) {
                    $top_cats[] = $categories[$i];
                }
            }
            $this->cache_category_links($id_list);
            if (isset($args['top_level_categories'])) {
                return $top_cats;
            }
        }

        if ( isset($args['sort_by']) && 'user_custom' != $sort_by ) {
            usort($categories, function($a,$b) use ($sort_by) {
                return strcmp($a->$sort_by,$b->$sort_by);
            });
            if($sort_order == 'desc') {
                $categories = array_reverse($categories);
            }
        }

        return $categories;
    }

    public function fetch_page($eid) {
        if ( isset( $this->_entry_id_cache[$eid] ) && !empty( $this->_entry_id_cache[$eid] ) ) {
            return $this->_entry_id_cache[$eid];
        }
        require_once("class.mt_page.php");
        $page = New Page;
        $page->LoadByIntId($eid);
        if ( !empty( $page ) ) {
            $this->_entry_id_cache[$eid] = $page;
            return $page;
        } else {
            return null;
        }
    }

    public function fetch_author($author_id) {
        if (isset($this->_author_id_cache[$author_id])) {
            return $this->_author_id_cache[$author_id];
        }
        $args['author_id'] = $author_id;
        $args['any_type'] = 1;
        $authors = $this->fetch_authors($args);
        $author = isset($authors[0]) ? $authors[0] : null;
        $this->_author_id_cache[$author_id] = $author;
        return $author;
    }

    public function fetch_author_by_name($author_name) {
        $mt = MT::get_instance();
        $args['blog_id'] = $mt->get_current_blog_id();
        $args['author_name'] = $author_name;
        $authors = $this->fetch_authors($args);
        if (!isset($authors[0])) {
            return;
        }
        $author = isset($authors[0]) ? $authors[0] : null;
        $this->_author_id_cache[$author->author_id] = $author;
        return $author;
    }

    public function fetch_authors($args) {
        # Adds blog join
        $extras = array();
        $mt = MT::get_instance();
        $ctx = $mt->context();

        # Adds author filter
        $author_filter = '';
        $bind_author_filter = [];
        if (isset($args['author_id'])) {
            $author_id = intval($args['author_id']);
            $author_filter .= " and author_id = ". $this->ph('author_id', $bind_author_filter, $author_id);
        }
        if (isset($args['author_nickname'])) {
            $author_filter .= " and author_nickname = ".
                                            $this->ph('author_nickname', $bind_author_filter, $args['author_nickname']);
        }
        if (isset($args['author_name'])) {
            $author_filter .= " and author_name = ".
                                            $this->ph('author_name', $bind_author_filter, $args['author_name']);
        }

        # Adds entry/cd join and filter
        $content_type = $ctx->stash('content_type');
        if (isset($content_type)) {
            if (isset($args['any_type']) && $args['any_type'] && !isset($args['need_content']))
                $args['need_content'] = 0;
            if (!isset($args['need_content']))
                $args['need_content'] = 1;
        }
        else {
            if (isset($args['any_type']) && $args['any_type'] && !isset($args['need_entry']))
                $args['need_entry'] = 0;
            if (!isset($args['need_entry']))
                $args['need_entry'] = 1;
        }
        if (!empty($args['need_entry']) && !(isset($args['id']) || isset($args['username']))) {
            $extras['join']['mt_entry'] = array(
                    'condition' => "author_id = entry_author_id"
                );
            $extras['distinct'] = 'distinct';
            $entry_filter = " and entry_status = 2";
            $entry_filter .= " and entry_blog_id ".
                                            $this->_include_exclude_blogs('entry_blog_id', $bind_entry_filter, $args);
        }
        elseif (!empty($args['need_content']) && !(isset($args['id']) || isset($args['username']))) {
            $extras['join']['mt_cd'] = array(
                    'condition' => "author_id = cd_author_id"
                );
            $extras['distinct'] = 'distinct';
            $cd_filter = " and cd_status = 2";
            $cd_filter .= " and cd_blog_id " . $this->_include_exclude_blogs('cd_blog_id', $bind_cd_filter, $args);
            if (isset($content_type)) {
                $cd_filter .= " and cd_content_type_id = ".
                                                $this->ph('cd_content_type_id', $bind_cd_filter, $content_type->id);
            }
        } else {
            $extras['distinct'] = 'distinct';
            if (!isset($args['roles']) and !isset($args['role'])) {
                $bind_join = [];
                $join_sql = "permission_author_id = author_id";
                if ( isset($args['need_association']) && $args['need_association'] ) {
                    $join_sql .= " and permission_blog_id ".
                                                $this->_include_exclude_blogs('permission_blog_id1', $bind_join, $args);
                }
                $join_blog_filter = $this->_include_exclude_blogs('permission_blog_id2', $bind_join, $args);
                if ( empty($args['any_type']) ) {
                    $join_sql .= "
                        and (
                            (
                                permission_blog_id $join_blog_filter
                                and (
                                    permission_permissions like '%create_post%' or
                                    permission_permissions like '%publish_post%'
                                )
                            ) or (
                                permission_blog_id = 0
                                and 
                                    permission_permissions like '%administer%'  
                           )
                       )
                    ";
                } else {
                    $join_sql .= "
                        and
                            (
                                (
                                    permission_blog_id $join_blog_filter
                                    and permission_permissions is not null
                                )
                            or
                                (
                                    permission_blog_id = 0
                                    and
                                        (
                                            permission_permissions like '%administer%'
                                            or
                                            permission_permissions like '%comment%'
                                        )
                                )
                            )
                        ";
                }
                
                $sql = $join_sql;
                $extras['join']['mt_permission'] = array(
                    'condition' => $sql,
                    'bind' => $bind_join,
                );
            }
        }

        # a context hash for filter routines
        $ctx = array();
        $filters = array();

        if (isset($args['status'])) {
            $status_arg = $args['status'];
            require_once("MTUtil.php");
            $status = array(
                array('name' => 'enabled', 'id' => 1),
                array('name' => 'disabled', 'id' => 2));

            $cexpr = create_status_expr_function($status_arg, $status);
            if ($cexpr) {
                $filters[] = $cexpr;
            }
        }

        if (isset($args['roles']) or isset($args['role'])) {
            $role_arg = isset($args['role']) ? $args['role'] : $args['roles'];
            require_once("MTUtil.php");
            $roles = $this->fetch_all_roles();
            if (!is_array($roles)) $roles = array();

            $cexpr = create_role_expr_function($role_arg, $roles);
            if ($cexpr) {
                $rmap = array();
                $role_list = array();
                foreach ($roles as $role) {
                    $role_list[] = $role->role_id;
                }
                $as = $this->fetch_associations(array(
                    'role_id' => $role_list
                ));
                foreach ($as as $a) {
                    if (($a->association_type == 2) || ($a->association_type == 5)) {
                        $as2 = $this->fetch_associations(
                            array('group_id' => array($a->association_group_id), 'type' => 3, 'blog_id' => 0));
                        foreach ($as2 as $a2) {
                            $aid = $a2->association_author_id;
                            if (!isset($rmap[$aid][$a->association_role_id])) {
                                $rmap[$aid][$a->association_role_id] = 0;
                            }
                            $rmap[$aid][$a->association_role_id]++;
                        }
                    }
                    else {
                        $aid = $a->association_author_id;
                        if (!isset($rmap[$aid][$a->association_role_id])) {
                            $rmap[$aid][$a->association_role_id] = 0;
                        }
                        $rmap[$aid][$a->association_role_id]++;
                    }
                }
                $ctx['r'] =& $rmap;
                $filters[] = $cexpr;
            }
        }

        # Adds a score or rate filter to the filters list.
        $re_sort = false;
        if (isset($args['namespace'])) {
            if (isset($args['scoring_to'])) {
                require_once("MTUtil.php");
                require_once("rating_lib.php");
                $type = $args['scoring_to'];
                $obj = $args['_scoring_to_obj'];
                $field_name = $type.'_id';
                $obj_id = $obj->$field_name;
                if (isset($args['min_score'])) {
                    $fn = function(&$e, &$c) use ($obj_id, $args) {
                        $ctx = $c;
                        if ($ctx == null) {
                            $mt = MT::get_instance();
                            $ctx = $mt->context();
                        }
                        $sc = get_score($ctx, $obj_id, $type, $args['namespace'], $e->author_id);
                        $ret = $sc >= $args['min_score'];
                        return $ret;
                    };
                    $filters[] = $fn;
                } elseif (isset($args['max_score'])) {
                    $fn = function(&$e, &$c) use ($obj_id, $args) {
                        $ctx = $c;
                        if ($ctx == null) {
                            $mt = MT::get_instance();
                            $ctx = $mt->context();
                        }
                        $sc = get_score($ctx, $obj_id, $type, $args['namespace'], $e->author_id);
                        $ret = $sc <= $args['max_score'];
                        return $ret;
                    };
                    $filters[] = $fn;
                }
                else {
                    $fn = function(&$e, &$c) use ($obj_id, $args, $type) {
                        $ctx = $c;
                        if ($ctx == null) {
                            $mt = MT::get_instance();
                            $ctx = $mt->context();
                        }
                        $ret = !is_null($ctx->mt->db()->fetch_score($args['namespace'], $obj_id, $e->author_id, $type));
                        return $ret;
                    };
                    $filters[] = $fn;
                }
            }
            else {
                require_once("MTUtil.php");
                $arg_names = array('min_score', 'max_score', 'min_rate', 'max_rate', 'min_count', 'max_count' );
                foreach ($arg_names as $n) {
                    if (isset($args[$n])) {
                        $rating_args = $args[$n];
                        $cexpr = create_rating_expr_function($rating_args, $n, $args['namespace'], 'author');
                        if ($cexpr) {
                            $filters[] = $cexpr;
                            $re_sort = true;
                        } else {
                            return null;
                        }
                    }
                }
            }
        }

        # sort
        $join_score = "";
        if (isset($args['sort_by'])) {
            if (($args['sort_by'] == 'score') || ($args['sort_by'] == 'rate')) {
                $extras['join']['mt_objectscore'] = array(
                    'condition' => "objectscore_object_id = author_id and objectscore_namespace='".$args['namespace']."' and objectscore_object_ds='author'"
                    );
                $extras['distinct'] = "distinct";
                $order_sql = "order by author_created_on desc";
                $re_sort = true;
            } else {
                if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                    throw new MTDBException('illegal sort_by name');
                }
                $sort_col = $args['sort_by'];
                if (strtolower($sort_col) == 'display_name') $sort_col = 'nickname';
                if (!preg_match('/^author_/i', $sort_col)) $sort_col = 'author_' . $sort_col;
                $order = '';
                if (isset($args['sort_order'])) {
                    if ($args['sort_order'] == 'ascend')
                        $order = 'asc';
                    else
                        $order = 'desc';
                }
                $order_sql = "order by $sort_col $order";
    
                if (isset($args['start_string'])) {
                    $val = $args['start_string'];
                    if ($order == 'asc')
                        $val_order = '>';
                    else
                        $val_order = '<';
                    $sort_filter =  " and $sort_col $val_order ". $this->ph('sort_start_string', $bind_sort_filter, $val);
                }
    
                if (isset($args['start_num'])) {
                    $val = $args['start_num'];
                    if ($order == 'asc')
                        $val_order = '>';
                    else
                        $val_order = '<';
                    $sort_filter .= " and $sort_col $val_order ". $this->ph('sort_start_num', $bind_sort_filter, $val);
                }
            }
        }

        $limit = 0;
        $offset = 0;
         if (isset($args['limit']))
            $limit = $args['limit'];

         $lastn = isset($args['lastn']) ? $args['lastn'] : 0;
        if ($re_sort) {
            $post_select_limit = $lastn;
            $lastn = 0;
            $post_select_offset = isset($args['offset']) ? $args['offset'] : 0;
        }

        $bind = array_merge(
            isset($bind_author_filter) ? $bind_author_filter : [],
            isset($bind_entry_filter) ? $bind_entry_filter : [],
            isset($bind_cd_filter) ? $bind_cd_filter : [],
            isset($bind_sort_filter) ? $bind_sort_filter : [],
            isset($bind_order_sql) ? $bind_order_sql : []
        );
        $where = implode(' ', array("1 = 1", 
            isset($author_filter) ? $author_filter : '',
            isset($entry_filter) ? $entry_filter : '',
            isset($cd_filter) ? $cd_filter : '',
            isset($sort_filter) ? $sort_filter : '',
            isset($order_sql) ? $order_sql : ''
        ));
        if ($limit) $extras['limit'] = $limit;

        require_once('class.mt_author.php');
        $author = new Author;
        $results = $author->Find($where, $bind, false, $extras);
        $authors = array();
        if (empty($args['sort_by']) || $args['sort_by'] != 'score' && $args['sort_by'] != 'rate') {
            $offset = isset($post_select_offset) ? $post_select_offset : 0;
            $limit = isset($post_select_limit) ? $post_select_limit : 0;
        }
        $j = 0;
        $i = -1;
        while (true) {
            $i++;
            if ($offset && ($j++ < $offset)) continue;
            $e = isset($results[$i]) ? $results[$i] : null;
            if (empty($e)) break;
            if (count($filters)) {
                foreach ($filters as $f) {
                    if (!$f($e, $ctx)) continue 2;
                }
            }
            $authors[] = $e;
            if (($lastn > 0) && (count($authors) >= $lastn)) break;
        }

        if (isset($args['sort_by']) && ('score' == $args['sort_by'])) {
            $authors_tmp = array();
            $order = 'desc';
            if (isset($args['sort_order']))
                $order = $args['sort_order'] == 'ascend' ? 'asc' : 'desc';
            foreach ($authors as $a) {
                $authors_tmp[$a->author_id] = $a;
            }
            $scores = $this->fetch_sum_scores(
                $args['namespace'],
                'author',
                $order,
                isset($author_filter) ? $author_filter : null,
                $bind_author_filter
            );
            $offset = $post_select_offset ? $post_select_offset : 0;
            $limit = $post_select_limit ? $post_select_limit : 0;
            $j = 0;
            $authors_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $authors_tmp)) {
                    if ($offset && ($j++ < $offset)) continue;
                    array_push($authors_sorted, $authors_tmp[$score['objectscore_object_id']]);
                    unset($authors_tmp[$score['objectscore_object_id']]);
                    if (($limit > 0) && (count($authors_sorted) >= $limit)) break;
                }
            }
            $authors = $authors_sorted;

        } elseif (isset($args['sort_by']) && ('rate' == $args['sort_by'])) {
            $authors_tmp = array();
            $order = 'asc';
            if (isset($args['sort_order']))
                $order = $args['sort_order'] == 'ascend' ? 'asc' : 'desc';
            foreach ($authors as $a) {
                $authors_tmp[$a->author_id] = $a;
            }
            $scores = $this->fetch_avg_scores($args['namespace'], 'author', $order, $author_filter, $bind_author_filter);
            $offset = $post_select_offset ? $post_select_offset : 0;
            $limit = $post_select_limit ? $post_select_limit : 0;
            $j = 0;
            $authors_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $authors_tmp)) {
                    if ($offset && ($j++ < $offset)) continue;
                    array_push($authors_sorted, $authors_tmp[$score['objectscore_object_id']]);
                    unset($authors_tmp[$score['objectscore_object_id']]);
                    if (($limit > 0) && (count($authors_sorted) >= $limit)) break;
                }
            }
            $authors = $authors_sorted;

        }

        return $authors;
    }

    public function fetch_permission($args) {
        $bind = [];
        $cond = [];
        // Blog filter
        $cond[] = 'permission_blog_id ' . $this->_include_exclude_blogs('permission_blog_id', $bind, $args);

        // Author filter
        if (isset($args['id'])) {
            $cond[] = 'permission_author_id = '. $this->ph('permission_author_id', $bind, $args['id']);
        }

        require_once('class.mt_permission.php');
        $perm = new Permission;
        $result = $perm->Find(implode(' and ', $cond). ' order by permission_id asc', $bind);
        return $result;
    }

    public function fetch_all_roles() {
        require_once('class.mt_role.php');
        $role = new Role;
        $result = $role->Find('1 = 1 order by role_name');
        return $result;
    }

    public function fetch_associations($args) {
        $cond = [];
        $bind = [];
        if (isset($args['role_id'])) {
            $ph = $this->in_ph('association_role_id', $bind, $args['role_id']);
            $cond[] = "association_role_id in ($ph)";
        }
        if (isset($args['group_id'])) {
            $ph = $this->in_ph('association_group_id', $bind, $args['group_id']);
            $cond[] = "association_group_id in ($ph)";
        }
        if (isset($args['type'])) {
            $cond[] = "association_type=". $this->ph('association_type', $bind, intval($args['type']));
        }
        if (empty($cond)) {
            return;
        }

        // Blog Filter
        $cond[] = 'association_blog_id ' . $this->_include_exclude_blogs('association_blog_id', $bind, $args);

        require_once('class.mt_association.php');
        $assoc = new Association;
        $where = implode(' and ', $cond);
        $result = $assoc->Find($where, $bind);
        if (!$result)
            return array();
        return $result;
    }

    public function fetch_tag($tag_id) {
        $tag_id = intval($tag_id);
        if (isset($this->_tag_id_cache[$tag_id])) {
            return $this->_tag_id_cache[$tag_id];
        }

        require_once('class.mt_tag.php');
        $tag = new Tag;
        $loaded = $tag->LoadByIntId($tag_id);
        if ($loaded)
            $this->_tag_id_cache[$tag->tag_id] = $tag;

        return $loaded ? $tag : null;
    }

    public function fetch_tag_by_name($tag_name) {

        require_once('class.mt_tag.php');
        $tag = new Tag;
        $loaded = $tag->Load("tag_name = ". $this->ph('tag_name', $bind, $tag_name), $bind);
        if ($loaded)
            $this->_tag_id_cache[$tag->tag_id] = $tag;

        return $loaded ? $tag : null;
    }

    public function fetch_scores($namespace, $obj_id, $datasource) {

        $cond[] = 'objectscore_namespace = '. $this->ph('objectscore_namespace', $bind, $namespace);
        $cond[] = 'objectscore_object_id = '. $this->ph('objectscore_object_id', $bind, intval($obj_id));
        $cond[] = 'objectscore_object_ds = '. $this->ph('objectscore_object_ds', $bind, $datasource);

        require_once('class.mt_objectscore.php');
        $score = new ObjectScore;
        $result = $score->Find(implode(' and ', $cond), $bind);
        return $result;
    }

    public function fetch_score($namespace, $obj_id, $user_id, $datasource) {

        $cond = [];
        $cond[] = 'objectscore_namespace = '. $this->ph('objectscore_namespace', $bind, $namespace);
        $cond[] = 'objectscore_object_id = '. $this->ph('objectscore_object_id', $bind, intval($obj_id));
        $cond[] = 'objectscore_object_ds = '. $this->ph('objectscore_object_ds', $bind, $datasource);
        $cond[] = 'objectscore_author_id = '. $this->ph('objectscore_author_id', $bind, intval($user_id));

        require_once('class.mt_objectscore.php');
        $score = new ObjectScore;
        $loaded = $score->Load(implode(' and ', $cond), $bind);
        return $loaded ? $score : null;
    }

    public function fetch_sum_scores($namespace, $datasource, $order, $filters, $bind=[]) {
        if (preg_match('/[^a-zA-Z0-9_]/', $datasource)) {
            throw new MTDBException('illegal datasource name');
        }
        $othertables = '';
        $otherwhere = '';
        if ($datasource == 'asset') {
            $othertables = ', mt_author';
            $otherwhere = 'AND (objectscore_author_id = author_id)';
        }
        $join_column = $datasource . '_id';
        $join_where = "AND ($join_column = objectscore_object_id)";
        $sql_scores = implode(' ', [
            "SELECT SUM(objectscore_score) AS sum_objectscore_score, objectscore_object_id
                FROM mt_objectscore, mt_$datasource $othertables",
            sprintf(
                "WHERE (objectscore_namespace = %s) AND (objectscore_object_ds = %s)",
                $this->ph('objectscore_namespace', $bind_local, $namespace),
                $this->ph('objectscore_object_ds', $bind_local, $datasource)
            ),
            $join_where,
            $otherwhere,
            $filters,
            "GROUP BY objectscore_object_id
                ORDER BY sum_objectscore_score " . $order
        ]);

        $scores = $this->Execute($sql_scores, array_merge($bind_local ?? [], $bind ?? []));
        return $scores;
    }

    public function fetch_avg_scores($namespace, $datasource, $order, $filters, $bind=[]) {
        if (preg_match('/[^a-zA-Z0-9_]/', $datasource)) {
            throw new MTDBException('illegal datasource name');
        }
        $othertables = '';
        $otherwhere = '';
        if ($datasource == 'asset') {
            $othertables = ', mt_author';
            $otherwhere = 'AND (objectscore_author_id = author_id)';
        }
        $join_column = $datasource . '_id';
        $join_where = "AND ($join_column = objectscore_object_id)";
        $sql_scores = implode(' ', [
            "SELECT AVG(objectscore_score) AS sum_objectscore_score, objectscore_object_id
                FROM mt_objectscore, mt_$datasource $othertables",
            sprintf(
                'WHERE (objectscore_namespace = %s) AND (objectscore_object_ds = %s)',
                $this->ph('objectscore_namespace', $bind_local, $namespace),
                $this->ph('objectscore_object_ds', $bind_local, $datasource)
            ),
            $join_where,
            $otherwhere,
            $filters,
            "GROUP BY objectscore_object_id ORDER BY sum_objectscore_score " . $order
        ]);

        $scores = $this->Execute($sql_scores, array_merge($bind_local ?? [], $bind ?? []));
        return $scores;
    }

    public function cache_permalinks($entry_list) {
        $ids = array();
        foreach ($entry_list as $entry_id) {
            if (!isset($this->_entry_link_cache[$entry_id.';Individual'])) {
                $ids[] = $entry_id;
                $this->_entry_link_cache[$entry_id.';Individual'] = ''; 
            }
        }
        if (empty($ids))
            return;

        $ph = $this->in_ph('fileinfo_entry_id', $bind, $ids);
        if (empty($ph)) {
            return;
        }

        $query = "
            select fileinfo_entry_id, fileinfo_url, A.blog_site_url as blog_site_url, A.blog_file_extension as blog_file_extension, A.blog_archive_url as blog_archive_url, B.blog_site_url as website_url, A.blog_parent_id as blog_parent_id
            from mt_fileinfo, mt_templatemap, mt_blog A, mt_blog B
            where fileinfo_entry_id in ($ph)
            and fileinfo_archive_type = 'Individual'
            and A.blog_id = fileinfo_blog_id
            and templatemap_id = fileinfo_templatemap_id
            and templatemap_is_preferred = 1
            and (
             (
              A.blog_parent_id = B.blog_id
               and B.blog_class = 'website'
               and A.blog_class = 'blog'
             ) or (
              ( A.blog_parent_id is null or A.blog_parent_id = 0 )
               and A.blog_class = 'website'
             )
            )
        ";

        $results = $this->Execute($query, $bind);
        if (!empty($results)) {
            foreach ($results as $row) {
                $blog_url = $row['blog_archive_url'];
                if (empty($blog_url))
                    $blog_url = $row['blog_site_url'];

                if ($row['blog_parent_id'] > 0) {
                    preg_match('/^(https?):\/\/(.+)\/$/', $row['website_url'], $matches);
                    if ( count($matches) > 1 ) {
                        $site_url = preg_split( '/\/::\//', $blog_url );
                        if ( count($site_url) > 0 )
                            $path = $matches[1] . '://' . $site_url[0] . $matches[2] . '/' . $site_url[1];
                        else
                            $path = $row['website_url'] . $this->blog_url;
                    }
                    else {
                        $path = $row['website_url'] . $blog_url;
                    }
                } else {
                    $path = $row['blog_site_url'];
                }

                $blog_url = $path;
                $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
                $url = $blog_url . $row['fileinfo_url'];
                $url = _strip_index($url, array('blog_file_extension' => $row['blog_file_extension']));
                $this->_entry_link_cache[$row['fileinfo_entry_id'].';Individual'] = $url;
            }
        }

        return true;
    }

    public function cache_category_links($cat_list) {
        $ids = array();
        foreach ($cat_list as $cat_id) {
            if (!isset($this->_cat_link_cache[$cat_id])) {
                $ids[] = $cat_id;
                $this->_cat_link_cache[$cat_id] = '';
            }
        }
        if (empty($ids))
            return;
        $ph = $this->in_ph('fileinfo_category_id', $bind, $ids);
        if (empty($ph)) {
            return;
        }

        $query = "
            select fileinfo_category_id, fileinfo_url, A.blog_site_url as blog_site_url, A.blog_file_extension as blog_file_extension, A.blog_archive_url as blog_archive_url, B.blog_site_url as website_url, A.blog_parent_id as blog_parent_id
              from mt_fileinfo, mt_templatemap, mt_blog A, mt_blog B
             where fileinfo_category_id in ($ph)
               and (fileinfo_archive_type = 'Category' or fileinfo_archive_type = 'ContentType-Category')
              and A.blog_id = fileinfo_blog_id
               and templatemap_id = fileinfo_templatemap_id
               and templatemap_is_preferred = 1
               and (
                (
                 A.blog_parent_id = B.blog_id
                  and B.blog_class = 'website'
                  and A.blog_class = 'blog'
                ) or (
                 ( A.blog_parent_id is null or A.blog_parent_id = 0 )
                  and A.blog_class = 'website'
                )
               )
        ";
        $results = $this->Execute($query, $bind);
        if ($results) {
            foreach ($results as $row) {
                $blog_url = $row['blog_archive_url'];
                $blog_url or $blog_url = $row['blog_site_url'];

                if ($row['blog_parent_id'] > 0 ) {
                    preg_match('/^(https?):\/\/(.+)\/$/', $row['website_url'], $matches);
                    if ( count($matches) > 1 ) {
                        $site_url = preg_split( '/\/::\//', $blog_url );
                        if ( count($site_url) > 0 )
                            $path = $matches[1] . '://' . $site_url[0] . $matches[2] . '/' . $site_url[1];
                        else
                            $path = $row['website_url'] . $this->blog_url;
                    }
                    else {
                        $path = $row['website_url'] . $blog_url;
                    }
                } else {
                    $path = $row['blog_site_url'];
                }

                $blog_url = $path;
                $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
                $url = $blog_url . $row['fileinfo_url'];
                $url = _strip_index($url, array('blog_file_extension' => $row['blog_file_extension']));
                $this->_cat_link_cache[$row['fileinfo_category_id']] = $url;
            }
        }

        return true;
    }

    function blog_entry_count($args) {
        $cond = [];
        $cond[] = 'entry_status = 2';
        $cond[] = 'entry_class = '. $this->ph('entry_class', $bind, $args['class'] ?? 'entry');
        $cond[] = 'entry_blog_id ' . $this->_include_exclude_blogs('entry_blog_id', $bind, $args);

        if (isset($args['author_id'])) {
            $cond[] = 'entry_author_id = ' . $this->ph('entry_author_id', $bind, intval($args['author_id']));
        }

        require_once('class.mt_entry.php');
        $entry = new Entry;
        $result = $entry->count(['where' => implode(' and ', $cond), 'bind' => $bind]);
        return $result;
    }

    function author_content_count($args) {
        $blog_filter = 'and cd_blog_id ' . $this->_include_exclude_blogs('cd_blog_id', $bind, $args);
        $content_type_filter = '';
        if (isset($args['content_type_id'])) {
            $content_type_filter = 'and cd_content_type_id = '.
                                $this->ph('cd_content_type_id', $bind, intval($args['content_type_id']));
        }
        $author_filter = '';
        if (isset($args['author_id'])) {
            $author_filter = 'and cd_author_id = ' . $this->ph('cd_author_id', $bind, intval($args['author_id']));
        }

        $where = "cd_status = 2
                  $blog_filter
                  $content_type_filter
                  $author_filter";

        require_once('class.mt_content_data.php');
        $cd = new ContentData;
        $result = $cd->count(array('where' => $where, 'bind' => $bind));
        return $result;
    }

    public function blog_comment_count($args) {
        $bind = [];
        $blog_filter = 'and comment_blog_id ' . $this->_include_exclude_blogs('comment_blog_id', $bind, $args);

        $where = "entry_status = 2
                  and comment_visible = 1
                  $blog_filter";
        if (isset($args['top']) and $args['top'] == 1) {
            $where .= " and (comment_parent_id is NULL or comment_parent_id = 0)";
        }
        $join = array();
        $join['mt_entry'] =
            array(
                'condition' => 'comment_entry_id = entry_id'
            );
        require_once('class.mt_comment.php');
        $comment = new Comment;
        $result = $comment->count(['where' => $where, 'join' => $join, 'bind' => $bind ?? null]);
        return $result;
    }

    public function category_comment_count($args) {
        $cat_id = intval($args['category_id']);

        $where = "placement_category_id = $cat_id
              and entry_status=2
              and comment_visible=1";
        if (isset($args['top']) and $args['top'] == 1) {
            $where .= " and (comment_parent_id is NULL or comment_parent_id = 0)";
        }
        $join['mt_entry'] =
             array(
                'condition' => 'comment_entry_id = entry_id'
                );
        $join['mt_placement'] =
            array(
                'condition' => 'entry_id = placement_entry_id'
                );

        require_once('class.mt_comment.php');
        $comment = new Comment;
        $result = $comment->count(array('where' => $where, 'join' => $join));
        return $result;
    }

    public function blog_ping_count($args) {
        $blog_filter = 'and tbping_blog_id ' . $this->_include_exclude_blogs('tbping_blog_id', $bind, $args);

        $where = "tbping_visible = 1
                  $blog_filter";

        $join['mt_trackback'] =
            array(
                "condition" => "tbping_tb_id = trackback_id"
                );

        require_once('class.mt_tbping.php');
        $tbping = new TBPing;
        $result = $tbping->count(array('where' => $where, 'bind' => $bind, 'join' => $join));
        return $result;
    }

    public function blog_category_count($args) {
        $bind = [];
        $blog_filter = 'and category_blog_id ' . $this->_include_exclude_blogs('category_blog_id', $bind, $args);

        $where = "category_class = 'category'
                  $blog_filter";

        require_once('class.mt_category.php');
        $cat = new Category;
        $result = $cat->count(['where' => $where, 'bind' => $bind]);
        return $result;
    }

    public function tags_entry_count($tag_id, $class = 'entry') {
        $tag_id = intval($tag_id);

        $where = "objecttag_tag_id = $tag_id";

        if ($class == 'entry' or $class == 'page') {
            $where .= " and entry_status = 2 and entry_class = '$class'";
        }

        $ds = $class == 'page' ? 'entry' : $ds;
        $join['mt_objecttag'] = 
            array(
                "condition" => "{$ds}_id = objecttag_object_id and objecttag_object_datasource='$ds'"
                );

        require_once("class.mt_$class.php");
        $entry = new $class();
        $count = $entry->count(array('where' => $where, 'join' => $join));
        return $count;
    }

    public function entry_comment_count($entry_id) {
        $entry_id = intval($entry_id);
        if (isset($this->_comment_count_cache[$entry_id])) {
            return $this->_comment_count_cache[$entry_id];
        }

        require_once('class.mt_entry.php');
        $entry = new Entry;
        $loaded = $entry->LoadByIntId($entry_id);
        $count = 0;
        if ($loaded) {
            $count = $entry->entry_comment_count;
            $this->_comment_count_cache[$entry_id] = $count;
        }
        return $count;
    }

    public function author_entry_count($args) {

        $cond = [];
        $cond[] = 'entry_status = 2';
        $cond[] = 'entry_class = '. $this->ph('entry_class', $bind, $args['class'] ?? 'entry');
        $cond[] = 'entry_blog_id ' . $this->_include_exclude_blogs('entry_blog_id', $bind, $args);

        if (isset($args['author_id'])) {
            $cond[] = "entry_author_id = ". $this->ph('entry_author_id', $bind, intval($args['author_id']));
        }

        require_once('class.mt_entry.php');
        $entry = new Entry;
        $count = $entry->count(['where' => implode(' and ', $cond), 'bind' => $bind]);
        return $count;
    }

    public function fetch_placements($args) {
        if (empty($args['category_id'])) {
            return;
        }
        $ph = $this->in_ph('placement_category_id', $bind, $args['category_id']);
        if (empty($ph)) {
            return;
        }
        $where = "placement_category_id in ($ph) and entry_status = 2";
        $extras['join'] = array(
            'mt_entry' => array(
                'condition' => 'entry_id = placement_entry_id'
                )
            );

        require_once('class.mt_placement.php');
        $placement = new Placement;
        return $placement->Find($where, $bind, false, $extras);
    }

    public function fetch_objecttags($args) {
        $bind = [];
        if (empty($args['tag_id'])) {
            return;
        }
        $tag_id_ph = $this->in_ph('objecttag_tag_id', $bind, $args['tag_id']);
        if (empty($tag_id_ph)) {
            return;
        }

        $blog_filter = 'and objecttag_blog_id ' . $this->_include_exclude_blogs('objecttag_blog_id', $bind, $args);

        $extras = array();
        if (isset($args['datasource']) && strtolower($args['datasource']) == 'asset') {
            $datasource = $args['datasource'];
            $extras['join'] = array(
                'mt_asset' => array(
                    'condition' => 'asset_id = objecttag_object_id'
                    )
                );
        }
        elseif (isset($args['datasource']) && strtolower($args['datasource']) == 'content_data') {
            $datasource = $args['datasource'];
            $extras['join'] = array(
                'mt_cd' => array(
                    'condition' => 'cd_id = objecttag_object_id'
                    )
                );
            $object_filter = 'and cd_status = 2';
        } else {
            $datasource = 'entry';
            $extras['join'] = array(
                'mt_entry' => array(
                    'condition' => 'entry_id = objecttag_object_id'
                    )
                );
            $object_filter = 'and entry_status = 2';
        }

        require_once('class.mt_objecttag.php');
        $otag = new ObjectTag;

        $where = implode(' ', array(
            "objecttag_object_datasource ='$datasource'",
            "and objecttag_tag_id in ($tag_id_ph)",
            $blog_filter,
            isset($object_filter) ? $object_filter : ''
        ));

        return $otag->Find($where, $bind, false, $extras);
    }

    public function fetch_comments($args) {
        # load comments
        $extras = array();
        $filters = array();

        $entry_id = isset($args['entry_id']) ? intval($args['entry_id']) : 0;

        $blog_filter = 'and comment_blog_id ' . $this->_include_exclude_blogs('comment_blog_id', $bind, $args);
        if (isset($args['blog_id'])) {
            $blog = $this->fetch_blog(intval($args['blog_id']));
        }

        # Adds a score or rate filter to the filters list.
        if (isset($args['namespace'])) {
            require_once("MTUtil.php");
            $arg_names = array('min_score', 'max_score', 'min_rate', 'max_rate', 'min_count', 'max_count' );
            foreach ($arg_names as $n) {
                if (isset($args[$n])) {
                    $comment_args = $args[$n];
                    $cexpr = create_rating_expr_function($comment_args, $n, $args['namespace'], 'comment');
                    if ($cexpr) {
                        $filters[] = $cexpr;
                    } else {
                        return null;
                    }
                }
            }
            if (isset($args['scored_by'])) {
                $voter = $this->fetch_author_by_name($args['scored_by']);
                if (empty($voter)) {
                    echo "Invalid scored by filter: ".$args['scored_by'];
                    return null;
                }
                $cexpr = create_rating_expr_function($voter->author_id, 'scored_by', $args['namespace'], 'comment');
                if ($cexpr) {
                    $filters[] = $cexpr;
                } else {
                    return null;
                }
            }
        }

        $order = $query_order = 'asc';
        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'descend') {
                $order = $query_order = 'desc';
            }
        } elseif ((isset($blog) && isset($blog->blog_sort_order_comments)) && !isset($args['lastn'])) {
            if ($blog->blog_sort_order_comments == 'ascend') {
                $order = $query_order = 'asc';
            }
        }
        if (($order == 'asc') && (isset($args['lastn']) || isset($args['offset']))) {
            $reorder = 1;
            $query_order = 'desc';
        }

        if ($entry_id) {
            $entry_filter = " and comment_entry_id = $entry_id";
            $extras['join']['mt_entry'] = array(
                'condition' => "entry_id = comment_entry_id"
                );
        } else {
            $extras['join']['mt_entry'] = array(
                'condition' => "entry_id = comment_entry_id and entry_status = 2"
                );
        }

        $join_score = "";
        $distinct = "";
        if ( isset($args['sort_by'])
          && (($args['sort_by'] == 'score') || ($args['sort_by'] == 'rate')) ) {
             $extras['join']['mt_objectscore'] = array(
                 'condition' => "objectscore_object_id = comment_id and objectscore_namespace='".$args['namespace']."' and objectscore_object_ds='comment'"
                 );
             $extras['distinct'] = 'distinct';
        }

        $limit = 0;
        $offset = 0;
        if (isset($args['lastn']))
            $limit = $args['lastn'];
        if (isset($args['limit']))
            $limit = $args['limit'];
        if (isset($args['offset']))
            $offset = $args['offset'];
        if (count($filters)) {
            $post_select_limit = $limit;
            $post_select_offset = $offset;
            $limit = 0; $offset = 0;
        }
        if (isset($args['top']) and $args['top'] == 1)
            $top_only = " and (comment_parent_id is NULL or comment_parent_id = 0)";

        if ($limit) $extras['limit'] = $limit;
        if ($offset) $extras['offset'] = $offset;

        $where = implode(' ', array(
            "comment_visible = 1",
            isset($top_only) ? $top_only : '',
            isset($entry_filter) ? $entry_filter : '',
            isset($blog_filter) ? $blog_filter : '',
            "order by comment_created_on $query_order"
        ));

        require_once('class.mt_comment.php');
        $comment = new Comment;
        $result = $comment->Find($where, $bind, false, $extras);
        if (empty($result)) return null;

        $comments = array();
        $j = 0;
        $i = -1;
        while (true) {
            $i++;
            $e = isset($result[$i]) ? $result[$i] : null;
            if (empty($e)) break;
            if (count($filters)) {
                foreach ($filters as $f) {
                    if (!$f($e, $ctx)) continue 2;
                }
                if ($post_select_offset && ($j++ < $post_select_offset)) continue;
                if (($post_select_limit > 0) && (count($comments) >= $post_select_limit)) break;
            }
            $comments[] = $e;
        }

        if (isset($args['sort_by']) && ('score' == $args['sort_by'])) {
            $comments_tmp = array();
            foreach ($comments as $c) {
                $comments_tmp[$c->comment_id] = $c;
            }
            $scores = $this->fetch_sum_scores($args['namespace'], 'comment', $order,
                implode(' ', array(
                    isset($blog_filter) ? $blog_filter : '',
                    isset($entry_filter) ? $entry_filter : ''
                ))
            );
            $comments_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $comments_tmp)) {
                    array_push($comments_sorted, $comments_tmp[$score['objectscore_object_id']]);
                    unset($comments_tmp[$score['objectscore_object_id']]);
                }
            }
            foreach ($comments_tmp as $et) {
                array_push($comments_sorted, $et);
            }
            $comments = $comments_sorted;
        } elseif (isset($args['sort_by']) && ('rate' == $args['sort_by'])) {
            $comments_tmp = array();
            foreach ($comments as $c) {
                $comments_tmp[$c->comment_id] = $c;
            }
            $scores = $this->fetch_avg_scores($args['namespace'], 'comment', $order,
                $blog_filter . "\n" .
                $entry_filter . "\n"
            );
            $comments_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $comments_tmp)) {
                    array_push($comments_sorted, $comments_tmp[$score['objectscore_object_id']]);
                    unset($comments_tmp[$score['objectscore_object_id']]);
                }
            }
            foreach ($comments_tmp as $et) {
                array_push($comments_sorted, $et);
            }
            $comments = $comments_sorted;
        }

        if (!is_array($comments))
            return array();

        if (!empty($reorder) && !isset($args['sort_by'])) {  // lastn and ascending sort
            if ( $order == 'asc' ) {
                $resorting = function($a, $b) {
                    return strcmp($a->comment_created_on, $b->comment_created_on);
                };
            } else {
                $resorting = function($a, $b) {
                    return strcmp($b->comment_created_on, $a->comment_created_on);
                };
            }
            usort($comments, $resorting);
        }

        return $comments;
    }

    public function fetch_comment_parent($args) {
        if (!$args['parent_id'])
            return null;

        $parent_id = intval($args['parent_id']);

        $blog_filter = 'and comment_blog_id ' . $this->_include_exclude_blogs('comment_blog_id', $bind, $args);
        if (isset($args['blog_id'])) {
            $blog = $this->fetch_blog($args['blog_id']);
        }

        $where = sprintf("1 = 1
                        $blog_filter
                        and comment_id = %s
                        and comment_visible = 1", $this->ph('comment_id', $bind, $parent_id));
        require_once('class.mt_comment.php');
        $comment = new Comment;
        $comments = $comment->Find($where, $bind);
        if (!empty($comments))
            $comment = $comments[0];
        else
            $comment = null;

        return $comment;
    }

    public function fetch_comment_replies($args) {
        if (!$args['comment_id'])
            return array();

        $comment_id = intval($args['comment_id']);

        $blog_filter = 'and comment_blog_id ' . $this->_include_exclude_blogs('comment_blog_id', $bind, $args);
        if (isset($args['blog_id'])) {
            $blog = $this->fetch_blog($args['blog_id']);
        }

        $order = 'asc';
        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'descend') {
                $order = 'desc';
            }
        } elseif (isset($blog) && isset($blog->blog_sort_order_comments)) {
            if ($blog->blog_sort_order_comments == 'ascend') {
                $order = 'asc';
            }
        }
        if ($order == 'asc' && !empty($args['lastn'])) {
            $reorder = 1;
            $order = 'desc';
        }

        $where = "1 = 1
                  $blog_filter
                  and comment_parent_id = $comment_id
                  and comment_visible = 1
                  order by comment_created_on $order";

        $extras = array();
        if (isset($args['lastn']) && is_numeric($args['lastn']))
            $extras['limit'] = $args['lastn'];
        if (isset($args['offset']) && is_numeric($args['lastn']))
            $extras['offset'] = $args['offset'];

        require_once('class.mt_comment.php');
        $comment = new Comment;
        $comments = $comment->Find($where, $bind, false, $extras);
        if (empty($comments))
            return array();

        if (!empty($reorder)) {  // lastn and ascending sort
            $asc_created_on = function($a, $b) {
                return strcmp($a->comment_created_on, $b->comment_created_on);
            };
            usort($comments, $asc_created_on);
        }
  
        return $comments;
    }

    public function entry_ping_count($entry_id) {
        if (isset($this->_ping_count_cache[$entry_id])) {
            return $this->_ping_count_cache[$entry_id];
        }

        require_once('class.mt_entry.php');
        $entry = new Entry;
        $loaded = $entry->LoadByIntId($entry_id);
        $count = 0;
        if ($loaded) {
            $count = $entry->entry_ping_count;
            $this->_ping_count_cache[$entry_id] = $count;
        }

        return $count;
    }

    public function category_ping_count($cat_id) {
        $cat_id = intval($cat_id);

        $where = "trackback_category_id = $cat_id
                  and tbping_visible = 1";
        $join['mt_trackback'] = 
            array(
                'condition' => "tbping_tb_id = trackback_id "
                );

        require_once('class.mt_tbping.php');
        $tbping = new TBPing;
        $count = $tbping->count(array('where' => $where, 'join' => $join));
        return $count;
    }

    public function fetch_pings($args) {
        # load pings  
        $bind = [];
        $blog_filter = 'and tbping_blog_id ' . $this->_include_exclude_blogs('tbping_blog_id', $bind, $args);
        if (isset($args['blog_id'])) {
            $blog = $this->fetch_blog($args['blog_id']);
        }

        $order = isset($args['lastn']) ? 'desc' : 'asc';
        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'descend') {
                $order = 'desc';
            } elseif ($args['sort_order'] == 'ascend') {
                $order = 'asc';
            }
        }

        $extras = array();
        if (isset($args['entry_id'])) {
            $entry_filter = 'and trackback_entry_id = '.
                                                    $this->ph('trackback_entry_id', $bind, intval($args['entry_id']));
            $extras['join']['mt_trackback'] = array(
                'condition' => 'tbping_tb_id = trackback_id'
                );
        }

        $where = implode(' ', array(
            "tbping_visible = 1",
            isset($blog_filter) ? $blog_filter : '',
            isset($entry_filter) ? $entry_filter : '',
            "order by tbping_created_on $order"
        ));

        if (isset($args['lastn']) && is_numeric($args['lastn']))
            $extras['limit'] = $args['lastn'];
        if (isset($args['offset']) && is_numeric($args['lastn']))
            $extras['offset'] = $args['offset'];

        require_once('class.mt_tbping.php');
        $tbping = new TBPing;
        $results = $tbping->Find($where, $bind, false, $extras);
        return $results;
    }

    public function cache_categories($entry_list) {
        $ids = null;
        foreach ($entry_list as $entry_id) {
            if (!isset($this->_cat_id_cache['e'.$entry_id]))
                $ids[] = $entry_id;
            $this->_cat_id_cache['e'.$entry_id] = null;
        }
        if (empty($ids))
            return;

        $ph = $this->in_ph('placement_entry_id', $bind, $ids);
        if (empty($ph)) {
            return;
        }

        $where = "placement_entry_id in ($ph)
               and placement_is_primary = 1";

        $extras['join'] = array(
                'mt_category' => array(
                    'condition' => "placement_category_id = category_id"
                    )
                );

        require_once('class.mt_placement.php');
        $placement = new Placement;
        $rows = $placement->Find($where, $bind, false, $extras);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $entry_id = $row->entry_id;
                $this->_cat_id_cache['e'.$entry_id] = $row;
                $cat_id = $row->category_id;
                if (!isset($this->_cat_id_cache['c'.$cat_id])) {
                    $cat = $row->category();
                    $this->_cat_id_cache['c'.$cat_id] = $cat;
                }
            }
        }
        return true;
    }

    public function fetch_folder($cat_id) {
        if (isset($this->_cat_id_cache['c'.$cat_id])) {
            return $this->_cat_id_cache['c'.$cat_id];
        }

        $cats = $this->fetch_categories(array('category_id' => $cat_id, 'show_empty' => 1, 'class' => 'folder'));
        if (!empty($cats)) {
            $this->_cat_id_cache['c'.$cat_id] = $cats[0];
            return $cats[0];
        } else {
            return null;
        }
    }

    public function asset_count($args) {
        $bind = [];
        if (isset($args['blog_id'])) {
            $blog_filter = 'and asset_blog_id = '. $this->ph('asset_blog_id', $bind, intval($args['blog_id']));
        }

        # Adds a type filter
        if (isset($args['type'])) {
            $type_filter = "and asset_class =". $this->ph('asset_class', $bind, $args['type']);
        }

        $where = "asset_parent is NULL $blog_filter ". (isset($type_filter) ? $type_filter : '');

        require_once('class.mt_asset.php');
        $asset = new Asset;
        $count = $asset->count(['where' => $where, 'bind' => $bind]);
        return $count;
    }

    function fetch_assets($args) {
        # load assets
        $extras = array();
        $filters = array();

        $blog_filter = 'and asset_blog_id ' . $this->_include_exclude_blogs('asset_blog_id', $bind_blog_filter, $args);

        # Adds a thumbnail filter to the filters list.
        if(isset($args['exclude_thumb']) && $args['exclude_thumb']) {
           $thumb_filter = ' and asset_parent is null'; 
        }
        
        # Adds a tag filter to the filters list.
        if (isset($args['tags']) or isset($args['tag'])) {
            $tag_arg = isset($args['tag']) ? $args['tag'] : $args['tags'];
            require_once("MTUtil.php");
            if (!preg_match('/\b(AND|OR|NOT)\b|\(|\)/i', $tag_arg)) {
                $not_clause = false;
            } else {
                $not_clause = preg_match('/\bNOT\b/i', $tag_arg);
            }

            $include_private = 0;
            $tag_array = tag_split($tag_arg);
            foreach ($tag_array as $tag) {
                if ($tag && (substr($tag,0,1) == '@')) {
                    $include_private = 1;
                }
            }

            $tags = $this->fetch_asset_tags(array(
                'blog_id' => isset($blog_id) ? $blog_id : null,
                'tag' => $tag_arg, 'include_private' => $include_private
            ));
            if (!is_array($tags)) $tags = array();
            $cexpr = create_tag_expr_function($tag_arg, $tags, 'asset');
            if ($cexpr) {
                $tmap = array();
                $tag_list = array();
                foreach ($tags as $tag) {
                    $tag_list[] = $tag->tag_id;
                }
                $ot = $this->fetch_objecttags(array('tag_id' => $tag_list, 'datasource' => 'asset'));
                if (!empty($ot)) {
                    foreach ($ot as $o) {
                        $oid = $o->objecttag_object_id;
                        if (!isset($tmap[$oid][$o->objecttag_tag_id])) {
                            $tmap[$oid][$o->objecttag_tag_id] = 0;
                        }
                        $tmap[$oid][$o->objecttag_tag_id]++;
                        if (!$not_clause)
                            $asset_list[$oid] = 1;
                    }
                }
                $ctx['t'] =& $tmap;
                $filters[] = $cexpr;
            } else {
                return null;
            }
        }

        # Adds an author filter
        if (isset($args['author'])) {
            $author_filter = "and author_name = ". $this->ph('author_name', $bind_author_filter, $args['author']);
            $extras['join']['mt_author'] = array(
                'condition' => "author_id = asset_created_by"
                );
        }

        # Adds an entry filter
        if (isset($args['entry_id'])) {
            $extras['join']['mt_objectasset'] = [
                'condition' => implode(' and ', [
                    "objectasset_object_ds = 'entry'",
                    "objectasset_object_id = ".
                        $this->ph('objectasset_object_id', $bind_entry_filter, intval($args['entry_id'])),
                    'asset_id = objectasset_asset_id',
                ]),
                'bind' => $bind_entry_filter,
            ];
        }

        # Adds an ID filter
        if ( isset($args['id']) ) {
            if ( $args['id'] == '' ) return null;
            $id_filter = 'and asset_id = '. $this->ph('asset_id', $bind_id_filter, intval($args['id']));
            $blog_filter = '';
            $bind_blog_filter = [];
        }

        # Adds a days filter
        if (isset($args['days'])) {
            $day_filter = 'and ' . $this->limit_by_day_sql('asset_created_on', intval($args['days']));
        }

        # Adds a type filter
        if (isset($args['type'])) {
            $type_filter = "and asset_class = ". $this->ph('asset_class', $bind_type_filter, $args['type']);
        }

        # Adds a file extension filter
        if (isset($args['file_ext'])) {
            $ext_filter = "and asset_file_ext = ". $this->ph('asset_file_ext', $bind_ext_filter, $args['file_ext']);
        }

        if (!empty($args['ignore_archive_context'])) {
            $date_filter = '';
        } else {
            $date_filter = $this->build_date_filter( $args, 'asset_created_on' );
        }

        # Adds a score or rate filter to the filters list.
        if (isset($args['namespace'])) {
            require_once("MTUtil.php");
            $arg_names = array('min_score', 'max_score', 'min_rate', 'max_rate', 'min_count', 'max_count' );
            foreach ($arg_names as $n) {
                if (isset($args[$n])) {
                    $rating_args = $args[$n];
                    $cexpr = create_rating_expr_function($rating_args, $n, $args['namespace'], 'asset');
                    if ($cexpr) {
                        $filters[] = $cexpr;
                    } else {
                        return null;
                    }
                }
            }

            if (isset($args['scored_by'])) {
                $voter = $this->fetch_author_by_name($args['scored_by']);
                if (!$voter) {
                    echo "Invalid scored by filter: ".$args['scored_by'];
                    return null;
                }
                $cexpr = create_rating_expr_function($voter->author_id, 'scored_by', $args['namespace'], 'asset');
                if ($cexpr) {
                    $filters[] = $cexpr;
                } else {
                    return null;
                }
            }
        }

        # Adds sort order
        $order = 'desc';
        $sort_by = 'asset_created_on';
        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'ascend')
                $order = 'asc';
            else if ($args['sort_order'] == 'descend')
                $order = 'desc';
        }
        
        if (isset($args['sort_by'])) {
            if ('score' != $args['sort_by'] && 'rate' != $args['sort_by']) {
                if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                    throw new MTDBException('illegal sort_by name');
                }
                $sort_by = 'asset_' . $args['sort_by'];
            }
        }
        if (isset($args['lastn'])) {
            $order = 'desc';
            $sort_by = 'asset_created_on';
        }

        $join_score = "";
        $distinct = "";
        if ( isset($args['sort_by'])
          && (($args['sort_by'] == 'score') || ($args['sort_by'] == 'rate')) ) {
             $extras['join']['mt_objectscore'] = array(
                 'type' => 'left',
                 'condition' => "objectscore_object_id = asset_id"
                 );
             $extras['distinct'] = 'distinct';
        }
        
        $limit = 0;
        $offset = 0;
        if (isset($args['lastn']))
            $limit = $args['lastn'];
        if (isset($args['limit']))
            $limit = $args['limit'];
        if (isset($args['offset']))
            $offset = $args['offset'];

        if (count($filters)) {
            $post_select_limit = $limit;
            $post_select_offset = $offset;
            $limit = 0; $offset = 0;
        }
        if ($limit) $extras['limit'] = $limit;
        if ($offset) $extras['offset'] = $offset;

        $bind = array_merge(
            isset($bind_id_filter) ? $bind_id_filter : [],
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_author_filter) ? $bind_author_filter : [],
            isset($bind_type_filter) ? $bind_type_filter : [],
            isset($bind_ext_filter) ? $bind_ext_filter : [],
            isset($bind_thumb_filter) ? $bind_thumb_filter : []
        );

        # Build SQL
        $where = implode(' ', array(
            "1 = 1",
            isset($id_filter) ? $id_filter : '',
            isset($blog_filter) ? $blog_filter : '',
            isset($author_filter) ? $author_filter : '',
            isset($day_filter) ? $day_filter : '',
            isset($type_filter) ? $type_filter : '',
            isset($ext_filter) ? $ext_filter : '',
            isset($thumb_filter) ? $thumb_filter : '',
            isset($date_filter) ? $date_filter : '',
            "order by $sort_by $order",
        ));

        require_once('class.mt_asset.php');
        $asset = new Asset;
        $result = $asset->Find($where, $bind, false, $extras);
        if (empty($result)) return null;

        $assets = array();
        $offset = !empty($post_select_offset) ? $post_select_offset : 0;
        $limit = !empty($post_select_limit) ? $post_select_limit : 0;

        $i = -1;
        while (true) {
            $i++;
            $e = isset($result[$i]) ? $result[$i] : null;
            if ($offset && ($j++ < $offset)) continue;
            if (!isset($e)) break;
            if (count($filters)) {
                foreach ($filters as $f) {
                    if (!$f($e, $ctx)) continue 2;
                }
            }
            $assets[] = $e;
            if (($limit > 0) && (count($assets) >= $limit)) break;
        }

        $no_resort = 0;
        if (isset($args['sort_by']) && $asset->has_column($args['sort_by'])) {
            $no_resort = 1;
        }
        if (isset($args['lastn'])) {
            if (isset($args['sort_by'])) {
                $no_resort = 0;
            }
        } else {
            if (!isset($args['sort_by'])) {
                $no_resort = 1;
            }
        }

        $order = 'desc';
        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'ascend')
                $order = 'asc';
            else if ($args['sort_order'] == 'descend')
                $order = 'desc';
        }

        $bind2 = array_merge(
            isset($bind_id_filter) ? $bind_id_filter : [],
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_author_filter) ? $bind_author_filter : [],
            isset($bind_type_filter) ? $bind_type_filter : [],
            isset($bind_ext_filter) ? $bind_ext_filter : [],
            isset($bind_thumb_filter) ? $bind_thumb_filter : []
        );

        # Resort assets
        if (isset($args['sort_by']) && ('score' == $args['sort_by'])) {
            $assets_tmp = array();
            foreach ($assets as $a) {
                $assets_tmp[$a->asset_id] = $a;
            }
            $scores = $this->fetch_sum_scores($args['namespace'], 'asset', $order,
                implode(' ', array(
                    isset($id_filter) ? $id_filter : '',
                    isset($blog_filter) ? $blog_filter : '',
                    isset($author_filter) ? $author_filter : '',
                    isset($day_filter) ? $day_filter : '',
                    isset($type_filter) ? $type_filter : '',
                    isset($ext_filter) ? $ext_filter : '',
                    isset($thumb_filter) ? $thumb_filter : ''
                )),
                $bind2
            );
            $assets_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $assets_tmp)) {
                    array_push($assets_sorted, $assets_tmp[$score['objectscore_object_id']]);
                    unset($assets_tmp[$score['objectscore_object_id']]);
                }
            }
            foreach ($assets_tmp as $et) {
                if ($order == 'asc')
                    array_unshift($assets_sorted, $et);
                else
                    array_push($assets_sorted, $et);
            }
            $assets = $assets_sorted;

        } elseif (isset($args['sort_by']) && ('rate' == $args['sort_by'])) {
            $assets_tmp = array();
            foreach ($assets as $a) {
                $assets_tmp[$a->asset_id] = $a;
            }
            $scores = $this->fetch_avg_scores($args['namespace'], 'asset', $order,
                $id_filter . "\n" .
                $blog_filter . "\n" .
                $author_filter . "\n" .
                $day_filter . "\n" .
                $type_filter . "\n" .
                $ext_filter . "\n" .
                $thumb_filter . "\n",
                $bind2
            );
            $assets_sorted = array();
            foreach($scores as $score) {
                if (array_key_exists($score['objectscore_object_id'], $assets_tmp)) {
                    array_push($assets_sorted, $assets_tmp[$score['objectscore_object_id']]);
                    unset($assets_tmp[$score['objectscore_object_id']]);
                }
            }
            foreach ($assets_tmp as $et) {
                if ($order == 'asc')
                    array_unshift($assets_sorted, $et);
                else
                    array_push($assets_sorted, $et);
            }
            $assets = $assets_sorted;

        } elseif (!$no_resort) {
            $sort_field = 'asset_created_on';
            if (isset($args['sort_by']) && $asset->has_column($args['sort_by'])) {
                if (preg_match('/^field[:\.](.+)$/', $args['sort_by'], $match)) {
                    // TODO: Unreachable because field.myfield causes SQL error earlier.
                    // Note that perl also doesn't seem to support field.myfield for assets
                    if (preg_match('/[^a-zA-Z0-9_]/', $match[1])) {
                        throw new MTDBException('illegal sort_by name');
                    }
                    $sort_field = 'asset_field.' . $match[1];
                } else {
                    if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                        throw new MTDBException('illegal sort_by name');
                    }
                    $sort_field = 'asset_' . $args['sort_by'];
                }
            }  
            if (   $sort_field == 'asset_blog_id'
                || $sort_field == 'asset_created_by'
                || $sort_field == 'asset_created_on'
                || $sort_field == 'asset_id'
                || $sort_field == 'asset_parent'
            ) {
                $sort_fn = function($a, $b) use ($sort_field) {
                    if ($a->$sort_field == $b->$sort_field) return 0;
                    return $a->$sort_field < $b->$sort_field ? -1 : 1;
                };
            } else {
                $sort_fn = function($a, $b) use ($sort_field) {
                    $f = addslashes($sort_field);
                    return strcmp($a->$f, $b->$f);
                };
            }
            if ($order == 'asc') {
                $sorter = function($a, $b) use ($sort_fn) {
                    return $sort_fn($a, $b);
                };
            } else {
                $sorter = function($b, $a) use ($sort_fn) {
                    return $sort_fn($a, $b);
                };
            }
            usort($assets, $sorter);
        }
        return $assets;
    }

    public function update_archive_link_cache($args) {
        $blog_id = $args['blog_id'];
        $at = $args['archive_type'];
        $where = isset($args['where']) ? $args['where'] : '';

        $cond = [];
        $cond[] = sprintf(
            "fileinfo_archive_type = %s and fileinfo_blog_id = %s and templatemap_is_preferred = 1",
            $this->ph('fileinfo_archive_type', $bind, $at),
            $this->ph('fileinfo_blog_id', $bind, $blog_id)
        );

        if (isset($args['hi']) && isset($args['low'])) {
            $cond[] = sprintf(
                "fileinfo_startdate between %s and %s",
                $this->ph('fileinfo_startdate1', $bind, $args['low']),
                $this->ph('fileinfo_startdate2', $bind, $args['hi'])
            );
        }

        $sql = implode(' ', [implode(' and ', $cond), $where]);
        $extras['join']['mt_templatemap'] = array(
            'condition' => "templatemap_id = fileinfo_templatemap_id"
            );

        require_once('class.mt_fileinfo.php');
        $fileinfo = new FileInfo();
        $finfos = $fileinfo->Find($sql, $bind, false, $extras);
        if (!empty($finfos)) {
            $mt = MT::get_instance();
            foreach($finfos as $finfo) {
                $date = $this->db2ts($finfo->fileinfo_startdate);
                if ($at == 'Page') {
                    $blog_url = $finfo->blog()->site_url();
                } else {
                    $blog_url = $finfo->blog()->archive_url();
                    $blog_url or $blog_url = $finfo->blog()->site_url();
                }
                $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
                $url = $blog_url . $finfo->url;
                $url = _strip_index($url, array('blog_file_extension' => $finfo->blog()->blog_file_extension));
                $this->_archive_link_cache[$date.';'.$at] = $url;
            }
        }
        return true;
    }

    protected function entries_recently_commented_on_sql($subsql) {
        $sql = "
            select distinct
                subs.*
            from
                ($subsql) subs
                inner join mt_comment on comment_entry_id = entry_id and comment_visible = 1
            order by
                comment_created_on desc
            <LIMIT>
        ";
        return $sql;
    }

    public function fetch_session($id) {
        $sessions = $this->fetch_unexpired_session($id);
        $session = null;
        if (!empty($sessions))
            $session = $sessions[0];
        return $session;
    }

    public function fetch_unexpired_session($ids, $ttl = 0) {
        $expire_sql = '';
        $bind = [];
        if (!empty($ttl) && $ttl > 0)
            $expire_sql = "and session_start >= " . (time() - $ttl);
        $key_sql = '';
        if (is_array($ids)) {
            $ph = $this->in_ph('session_id', $bind, $ids);
            $key_sql = 'and session_id in (' . $ph . ')';
        } else {
            $key_sql = "and session_id = ". $this->ph('session_id', $bind, $ids);
        }

        $where = "session_kind = 'CO'
                  $key_sql
                  $expire_sql";

        require_once('class.mt_session.php');
        $session = new Session;
        $sessions = $session->Find($where, $bind);
        return $sessions;
    }

    public function update_session($id, $val) {
        $session = $this->fetch_session($id);
        $now = time();
        if (empty($session)) {
            $session = new Session;
            $session->session_id = $id;
            $session->session_kind = 'CO';
        }
        $session->data( $val );
        $session->session_start = $now;
        $session->save();
        return true;
    }

    public function remove_session($id) {
        $session = $this->fetch_session($id);
        if (!empty($session))
            $session->Delete();

        return true;
    }

    public function flush_session() {
        $sql = "
            delete from mt_session
            where session_kind = 'CO'";
        $this->db()->Execute($sql);
        return true;
    }

    public function get_latest_touch($blog_id, $types) {
        $type_user = false;
        if (is_array($types)) {
            $array = preg_grep('/author/', $types);
            if (!empty($array)) $type_user = true;
        } else {
            $type_user = $types == 'author';
        }

        $bind = [];

        $blog_filter = '';
        if (!empty($blog_id)) {
            if ($type_user)
                $blog_filter = 'and touch_blog_id = 0';
            else {
                $blog_filter = 'and touch_blog_id = '. $this->ph('touch_blog_id', $bind, $blog_id);
            }
        }

        $type_filter = '';
        if (!empty($types)) {
            if ($type_user) {
                $type_filter = 'and touch_object_type =\'author\'';
            } else {
                if (is_array($types)) {
                    $ph = $this->in_ph('touch_object_type', $bind, $types);
                    $type_filter = 'and touch_object_type in (' . $ph . ')';
                } else {
                    $type_filter = 'and touch_object_type = '. $this->ph('touch_object_type', $bind, $types);
                }
            }
        }

        $where = "1 = 1
                  $blog_filter
                  $type_filter
                  order by
                    touch_modified_on desc";
        $extras['limit'] = 1;

        require_once('class.mt_touch.php');
        $touch = new Touch;
        $touches = $touch->Find($where, $bind, false, $extras);

        if (!empty($touches))
            return $touches[0];

        return false;
    }

    public function fetch_template_meta($type, $name, $blog_id, $global) {

        $cond = [];
        if ($type === 'identifier') {
            $cond[] = "template_identifier = ". $this->ph('template_identifier', $bind, $name);
        } else {
            $cond[] = "template_name = ". $this->ph('template_name', $bind, $name);
            $cond[] = "template_type=". $this->ph('template_type', $bind, $type);
        }
        if (!isset($global)) {
            $cond[] = 'template_blog_id in ('. $this->in_ph('template_blog_id', $bind, [$blog_id, 0]). ')';
        } elseif ($global) {
            $cond[] = 'template_blog_id = 0';
        } else {
            $cond[] = "template_blog_id = ". $this->ph('template_blog_id', $bind, $blog_id);
        }
        $where = implode(' and ', $cond). ' order by template_blog_id desc';

        require_once('class.mt_template.php');
        $tmpl = new Template();
        $tmpls = $tmpl->Find($where, $bind);
        if (empty($tmpls))
            $tmpl = null;
        else
            $tmpl = $tmpls[0];

        return $tmpl;
    }

    public function fetch_category_set($category_set_id) {
        if (!empty($this->_category_set_id_cache) && isset($this->_category_set_id_cache[$category_set_id])) {
            return $this->_category_set_id_cache[$category_set_id];
        }
        require_once('class.mt_category_set.php');
        $category_set = new CategorySet;
        $loaded = $category_set->LoadByIntId($category_set_id);
        if (!$loaded) return null;
        $this->_category_set_id_cache[$category_set_id] = $category_set;
        return $category_set;
    }

    public function fetch_category_sets($args) {
        $cond = [];
        $bind = [];
        $extras = array();
        if (!empty($args['limit']) && $args['limit'] > 0) {
            $extras['limit'] = $args['limit'];
        } else {
            $extras['limit'] = -1;
        }
        if ($args['blog_id'] && $args['blog_id'] > 0) {
            $cond[] = "category_set_blog_id = ". $this->ph('category_set_blog_id', $bind, $args['blog_id']);
        }
        if (isset($args['name']) && !empty($args['name'])) {
            $cond[] = "category_set_name = ". $this->ph('category_set_name', $bind, $args['name']);
        }
        if( isset($args['content_type']) ){
            $content_types = $this->fetch_content_types($args);
            $ct_ids = array();
            foreach ($content_types as $ct) {
                array_push($ct_ids, $ct->id);
            }
            if($content_types && count($ct_ids) > 0){
                $extras['join'] = array(
                    'mt_cf' => array(
                        'condition' => "cf_type = 'categories'",
                    )
                );
                $cond[] = 'cf_content_type_id in ('. $this->in_ph('cf_content_type_id', $bind, $ct_ids). ')';
                $cond[] = 'category_set_id = cf_related_cat_set_id';
            }
        }
        
        require_once('class.mt_category_set.php');
        $category_set = new CategorySet;
        return $category_set->Find(implode(' and ', $cond), $bind, false, $extras);
    }

    private function build_date_filter($args, $field) {
        $start = isset($args['current_timestamp'])
            ? $args['current_timestamp'] : null;
        $end = isset($args['current_timestamp_end'])
            ? $args['current_timestamp_end'] : null;

        if ($start and $end) {
            $start = $this->ts2db($start);
            $end = $this->ts2db($end);
            return "and $field between '$start' and '$end'";
        } elseif ($start) {
            $start = $this->ts2db($start);
            return "and $field >= '$start'";
        } elseif ($end) {
            $end = $this->ts2db($end);
            return "and $field <= '$end'";
        } else {
            return '';
        }
    }

    public function fetch_rebuild_trigger($blog_id) {
        if (!empty($this->_rebuild_trigger_cache) && isset($this->_rebuild_trigger_cache[$blog_id])) {
            return $this->_rebuild_trigger_cache[$blog_id];
        }
        require_once('class.mt_rebuild_trigger.php');
        $rebuild_trigger = new RebuildTrigger;
        $rebuild_trigger->Load("rebuild_trigger_blog_id = ".
                                                        $this->ph('rebuild_trigger_blog_id', $bind, $blog_id), $bind);
        $this->_rebuild_trigger_cache[$blog_id] = $rebuild_trigger;
        return $rebuild_trigger;
    }

    function fetch_content_type($id) {
        if ( isset( $this->_content_type_id_cache[$id] ) && !empty( $this->_content_type_id_cache[$id] ) ) {
            return $this->_content_type_id_cache[$id];
        }
        require_once("class.mt_content_type.php");
        $content_type= New ContentType;
        $loaded = $content_type->LoadByIntId($id);
        if ($loaded) {
            $this->_content_type_id_cache[$id] = $content_type;
            return $content_type;
        } else {
            return null;
        }
    }

    public function fetch_content_types($args) {
        require_once('class.mt_content_type.php');

        $bind_blog_filter = [];
        $blog_filter = 'content_type_blog_id ' . 
                                        $this->_include_exclude_blogs('content_type_blog_id', $bind_blog_filter, $args);

        $content_types= array();
        if (isset($args['content_type'])) {
            $str = $args['content_type'];
            if (isset($blog_filter))
                $blog_filter = 'and '.$blog_filter;
            if (ctype_digit(strval($str))) {
                $bind = [];
                $sql = sprintf("select
                            mt_content_type.*
                        from mt_content_type
                        where
                            content_type_id = %s
                            $blog_filter", $this->ph('content_type_id', $bind, $str));
                $result = $this->SelectLimit($sql, -1, -1, array_merge($bind, $bind_blog_filter));
            }
            if (!isset($result) || $result->EOF) {
                $bind = [];
                $sql = sprintf("select
                            mt_content_type.*
                        from mt_content_type
                        where
                            content_type_unique_id = %s
                            $blog_filter", $this->ph('content_type_unique_id', $bind, $str));
                $result = $this->SelectLimit($sql, -1, -1, array_merge($bind, $bind_blog_filter));
                if ($result->EOF) {
                    $bind = [];
                    $sql = sprintf("select
                                mt_content_type.*
                            from mt_content_type
                            where
                                content_type_name = %s
                                $blog_filter", $this->ph('content_type_name', $bind, $str));
                    $result = $this->SelectLimit($sql, -1, -1, array_merge($bind, $bind_blog_filter));
                }
                if ($result->EOF) return null;
            }
        } else {
            $sql = "select
                        mt_content_type.*
                    from mt_content_type
                    where
                        $blog_filter";
            $result = $this->SelectLimit($sql, -1, -1, $bind_blog_filter);
            if ($result->EOF) return null;
        }

        $field_names = array_keys($result->fields);

        $j = 0;
        while (!$result->EOF) {
            $ct = new ContentType;
            foreach($field_names as $key) {
                if (is_numeric($key)) continue;
  	            $key = strtolower($key);
                $ct->$key = $result->fields($key);
            }
            $result->MoveNext();

            if (empty($ct)) break;

            $ct->content_type_modified_on = $this->db2ts($ct->content_type_modified_on);
            $content_types[] = $ct;
        }

        return $content_types;
    }

    function fetch_content($cid) {
        if ( isset( $this->_cd_id_cache[$cid] ) && !empty( $this->_cd_id_cache[$cid] ) ) {
            return $this->_cd_id_cache[$cid];
        }
        require_once("class.mt_content_data.php");
        $cd = New ContentData;
        $cd->LoadByIntId($cid);
        if ( !empty( $cd) ) {
            $this->_cd_id_cache[$cid] = $cd;
            return $cd;
        } else {
            return null;
        }
    }

    public function fetch_contents($args, $content_type_id, &$total_count = NULL) {
        require_once('class.mt_content_data.php');
        $extras = array();
        $mt = MT::get_instance();
        $ctx = $mt->context();

        if (isset($args['site_id'])) {
            $blog_id = $args['site_id'];
        } elseif (isset($args['blog_id'])) {
            $blog_id = $args['blog_id'];
        } elseif ($ctx->stash('blog_id')) {
            $blog_id = $ctx->stash('blog_id');
        } elseif ($ctx->stash('blog')) {
            $blog = $ctx->stash('blog');
        }

        if (isset($blog_id)) {
            $blog = $this->fetch_blog($blog_id);
        } elseif (isset($blog)) {
            $blog_id = $blog->blog_id;
        }

        $blog_filter = 'and cd_blog_id = '. $this->ph('cd_blog_id', $bind_blog_filter, $blog_id);

        if (empty($blog))
            return null;

        if (isset($content_type_id)) {
            if (is_array($content_type_id)) {
                $ph = $this->in_ph('cd_content_type_id', $bind_content_type_filter, $content_type_id);
                $content_type_filter = "and cd_content_type_id in (" . $ph. ' )';
            } else {
                $content_type_filter = 'and cd_content_type_id = '.
                                        $this->ph('cd_content_type_id', $bind_content_type_filter, $content_type_id);
            }
        }

        // determine any content fields that we should filter on
        $fields = array();
        foreach ($args as $name => $v)
            if (preg_match('/^field___(\w+)$/', $name, $m))
                $fields[$m[1]] = $v;

        # automatically include offset if in request
        if (isset($args['offset']) && $args['offset'] == 'auto') {
            $args['offset'] = 0;
            if ($args['limit']) {
                if (intval($_REQUEST['offset']) > 0) {
                    $args['offset'] = intval($_REQUEST['offset']);
                }
            }
        }

        if (isset($args['limit']) && $args['limit'] == 'auto') {
            if (isset($_REQUEST['limit']) && intval($_REQUEST['limit']) > 0) {
                $args['limit'] = intval($_REQUEST['limit']);
            }
        }

        if (isset($args['include_blogs']) or isset($args['exclude_blogs'])) {
            $blog_ctx_arg = isset($args['include_blogs']) ?
                array('include_blogs' => $args['include_blogs']) :
                array('exclude_blogs' => $args['exclude_blogs']);
            $include_with_website = $args['include_parent_site'] || $args['include_with_website'];
            if (isset($args['include_blogs']) && isset($include_with_website)) {
                $blog_ctx_arg = array_merge($blog_ctx_arg, array('include_with_website' => $include_with_website));
            }
        }

        # a context hash for filter routines
        $filters = array();

        if (!isset($_REQUEST['content_ids_published'])) {
            $_REQUEST['content_ids_published'] = array();
        }

        if (isset($args['unique']) && $args['unique']) {
            $filter_ctx = array();
            $filter_func = function($cd, $ctx) {
                return !isset($ctx["content_ids_published"][$cd->cd_id]);
            };
            $filter_ctx['content_ids_published'] = &$_REQUEST['content_ids_published'];
            $filters[] = array($filter_func, $filter_ctx);
        }

        # special case for selecting a particular content
        if (isset($args['id'])) {
            $content_filter = 'and cd_id = '. $this->ph('cd_id', $bind_content_filter, $args['id']);
            $start = ''; $end = ''; $limit = 1; $blog_filter = ''; $day_filter = '';
            $bind_blog_filter = [];
        } else {
            $content_filter = '';
        }

        $content_list = array();

        if (count($content_list) && ($content_filter == '')) {
            // TODO: Unreachable becaulse $content_list is initialized right before the if-block. If it's still needed, fix it for MTC-27980.
            $content_list = implode(",", array_keys($content_list));
            # set a reasonable cap on the content list cache. if
            # user is selecting something too big, then they'll
            # just have to wait through a scan.
            if (strlen($content_list) < 2048)
                $content_filter = "and cd_id in ($content_list)";
        }

        if (isset($args['author'])) {
            $author_filter = 'and author_name = '. $this->ph('author_name', $bind_author_filter, $args['author']);
            $extras['join']['mt_author'] = array(
                    'condition' => "cd_author_id = author_id"
                    );
        } elseif (isset($args['author_id']) && preg_match('/^\d+$/', $args['author_id']) && $args['author_id'] > 0) {
            $author_filter = "and cd_author_id = ". $this->ph('cd_author_id', $bind_author_filter, $args['author_id']);
        }

        $join_clause = '';

        $map = $mt->db()->fetch_templatemap(array(
            'blog_id'         => $blog_id,
            'content_type_id' => $content_type_id,
            'preferred'       => 1,
            'type'            => $ctx->stash('current_archive_type'),
        ));

        if (isset($args['current_timestamp']) || isset($args['current_timestamp_end'])) {
            if ($map && ($dt_field_id = $map[0]->dt_field_id)) {
                $start = isset($args['current_timestamp'])
                    ? $args['current_timestamp'] : null;
                $end = isset($args['current_timestamp_end'])
                    ? $args['current_timestamp_end'] : null;
                $alias = 'cf_idx_' . $dt_field_id;
                $field = "$alias.cf_idx_value_datetime";
                $ph_prefix = preg_replace('/[\._]/', '', $field);
                if ($start and $end) {
                    $start = $this->ts2db($start);
                    $end = $this->ts2db($end);
                    $field_filter_ts = sprintf(
                        " and $field between %s and %s",
                        $this->ph($ph_prefix. '1', $bind_field_filter_ts, $start),
                        $this->ph($ph_prefix. '2', $bind_field_filter_ts, $end)
                    );
                } elseif ($start) {
                    $start = $this->ts2db($start);
                    $field_filter_ts = " and $field >= ". $this->ph($ph_prefix, $bind_field_filter_ts, $start);
                } elseif ($end) {
                    $end = $this->ts2db($end);
                    $field_filter_ts = " and $field <= ". $this->ph($ph_prefix, $bind_field_filter_ts, $end);
                } else {
                    return '';
                }
                $join_table = "mt_cf_idx $alias";
                $join_condition = "$alias.cf_idx_content_field_id = " . intval($dt_field_id) .
                                " and $alias.cf_idx_content_data_id = cd_id" .
                                $field_filter_ts;
                $extras['join'][$join_table] = ['condition' => $join_condition, 'bind' => $bind_field_filter_ts];
                if (isset($args['_current_timestamp_sort']) && $args['_current_timestamp_sort']) {
                    $sort_field = "$alias.cf_idx_value_datetime";
                }
            } else {
                $dt_field    = 'cd_authored_on';
                $date_filter = $this->build_date_filter($args, $dt_field);
            }
        }

        $dt_field    = 'cd_authored_on';
        $dt_field_id = 0;
        if ( isset($args['date_field']) && ($arg = $args['date_field']) ) {
            if (   $arg === 'authored_on'
                || $arg === 'modified_on'
                || $arg === 'created_on' )
            {
                $dt_field = 'cd_' . $arg;
            }
            else {
                if (preg_match('/^[0-9]+$/', $arg)) {
                    $date_cf = $this->fetch_content_field($arg);
                    if ($date_cf) {
                        $date_cfs = array($date_cf);
                    }
                }
                if (!isset($date_cfs))
                    $date_cfs = $this->fetch_content_fields(array('unique_id' => $arg));
                if (!isset($date_cfs))
                    $date_cfs = $this->fetch_content_fields(array(
                        'blog_id' => $blog_id,
                        'content_type_id' => $content_type_id,
                        'name' => $arg,
                    ));
                if (isset($date_cfs)) {
                    $date_cf = $date_cfs[0];
                    $date_cf_id = $date_cf->cf_id;
                    $type = $date_cf->cf_type;
                }
            }
            if (isset($date_cf)) {
                $alias = 'cf_idx_' . $date_cf_id;

                require_once "content_field_type_lib.php";
                $cf_type = ContentFieldTypeFactory::get_type($type);
                $data_type = $cf_type->get_data_type();

                $dt_field = "$alias.cf_idx_value_$data_type";

                $join_table = "mt_cf_idx $alias";
                $join_condition = "$alias.cf_idx_content_field_id = " . $date_cf_id .
                                  " and $alias.cf_idx_content_data_id = cd_id";
                $extras['join'][$join_table] = array('condition' => $join_condition);
            }
            $date_filter = $this->build_date_filter($args, $dt_field);
        }

        if (isset($args['days']) && empty($date_filter)) {
            $day_filter = 'and ' . $this->limit_by_day_sql($dt_field, intval($args['days']));
        } elseif (isset($args['limit'])) {
            if (!isset($args['id'])) $limit = $args['limit'];
        } else {
            $found_valid_args = 0;
            foreach ( array(
                'limit', 'days',
                'author',
              ) as $valid_key )
            {
                if (array_key_exists($valid_key, $args)) {
                    $found_valid_args = 1;
                    break;
                }
            }
        }

        if (isset($args['sort_order'])) {
            if ($args['sort_order'] == 'ascend') {
                $order = 'asc';
            } elseif ($args['sort_order'] == 'descend') {
                $order = 'desc';
            }
        } 
        if (!isset($order)) {
            $order = 'desc';
            if (isset($blog) && isset($blog->blog_sort_order_posts)) {
                if ($blog->blog_sort_order_posts == 'ascend') {
                    $order = 'asc';
                }
            }
        }

        if (isset($args['offset']))
            $offset = $args['offset'];

        if (!isset($sort_field)) {
            if (isset($args['sort_by']) || $map && $map[0]->dt_field_id) {
                if (!isset($args['sort_by'])) {
                    $cf = $map[0]->dt_field();
                } else if (preg_match('/^field:(.+)$/', $args['sort_by'], $m)) {
                    $key= $m[1];
                    $cfs = $this->fetch_content_fields(array(
                        'blog_id' => $blog_id,
                        'content_type_id' => $content_type_id,
                        'name' => $key
                    ));
                    if (!isset($cfs))
                        $cfs = $this->fetch_content_fields(array(
                            'unique_id' => $key
                        ));
                    if (isset($cfs)) {
                        $cf = $cfs[0];
                    }
                }
                if (isset($cf)) {
                    $type = $cf->cf_type;
                    require_once "content_field_type_lib.php";
                    $cf_type = ContentFieldTypeFactory::get_type($type);

                    $alias = 'sb_cf_idx_' . $cf->id;

                    $data_type = $cf_type->get_data_type();
                    $join_table = "mt_cf_idx $alias";
                    $join_condition = "$alias.cf_idx_content_field_id = " . $cf->cf_id .
                                      " and $alias.cf_idx_content_data_id = cd_id";
                    $extras['join'][$join_table] = array('condition' => $join_condition, 'type' => 'left');

                    $sort_field = "$alias.cf_idx_value_$data_type";
                    if ($data_type == 'text') {
                        $sort_field = $this->decorate_column($sort_field);
                    }
                    $no_resort = 1;
                }
                if (!isset($sort_field) && isset($args['sort_by'])) {
                    $sort_field = '';
                    if ($args['sort_by'] == 'authored_on') {
                        $sort_field = 'cd_authored_on';
                    } elseif ($args['sort_by'] == 'modified_on') {
                        $sort_field = 'cd_modified_on';
                    } elseif ($args['sort_by'] == 'created_on') {
                        $sort_field = 'cd_created_on';
                    } elseif ($args['sort_by'] == 'author_id') {
                        $sort_field = 'cd_author_id';
                    } elseif ($args['sort_by'] == 'identifier') {
                        $sort_field = 'cd_identifier';
                    } elseif (preg_match('/field[:\.]/', $args['sort_by'])) {
                        $post_sort_limit = !empty($limit) ? $limit : 0;
                        $post_sort_offset = !empty($offset) ? $offset : 0;
                        $limit = 0; $offset = 0;
                        $no_resort = 0;
                    } else {
                        if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                            throw new MTDBException('illegal sort_by name');
                        }
                        $sort_field = 'cd_' . $args['sort_by'];
                    }
                    if ($sort_field) $no_resort = 1;
                }
            }
            else {
                $sort_field = 'cd_authored_on';
            }
        }

        if ($sort_field) {
            $base_order = (
                isset( $args['sort_order'] )
                    ? $args['sort_order']
                    : ( isset( $args['base_sort_order'] )
                            ? $args['base_sort_order']
                            : '' )
            ) === 'ascend' ? 'asc' : 'desc';
        }

        $field_filter = '';
        if(isset($args['category_set'])){
            $id = $args['category_set'];

            if(preg_match('/^[0-9]+$/',$id))
                $category_set = $this->fetch_category_set($id);
            if(empty($category_set)){
                $category_sets = $this->fetch_category_sets(array(
                    'blog_id' => $blog_id,
                    'name' => $id,
                    'limit' => 1,
                ));
                if($category_sets)
                    $category_set = $category_sets[0];
            }
            if(isset($category_set)){
                $category_set_id = $category_set->id;
                
                $cat_fields = $this->fetch_content_fields(array(
                    'blog_id' => $blog_id,
                    'content_type_id' => $content_type_id,
                    'related_cat_set_id' => $category_set_id,
                ));
                if($cat_fields){
                    $cf = $cat_fields[0];
                    if (isset($args['category']) or $ctx->stash('category')){
                        if (!array_key_exists($cf->cf_name, $fields) && !array_key_exists($cf->cf_unique_id, $fields)) {
                            $fields[$cf->cf_name] = isset($args['category']) ? $args['category'] : $ctx->stash('category')->label;
                        }
                    } else {
                        $alias = 'cf_idx_' . $cf->id;
                        require_once "content_field_type_lib.php";
                        $cf_type = ContentFieldTypeFactory::get_type('categories');
                        $data_type = $cf_type->get_data_type();

                        $join_table = "mt_cf_idx $alias";
                        $join_condition = "$alias.cf_idx_content_field_id = " . $cf->id .
                                          " and $alias.cf_idx_content_data_id = cd_id\n";
                        $extras['join'][$join_table] = array('condition' => $join_condition);
                    }
                }
            }
        }

        if (count($fields)) {
            foreach ($fields as $key => $value) {
                $cfs = $this->fetch_content_fields(array(
                    'blog_id' => $blog_id,
                    'content_type_id' => $content_type_id,
                    'name' => $key,
                ));
                if (!isset($cfs))
                    $cfs = $this->fetch_content_fields(array('unique_id' => $key));
                if (!isset($cfs)) continue;
                
                $cf = $cfs[0];
                $type = $cf->cf_type;

                if ($type === 'categories' && !(array_key_exists('_no_use_category_filter', $args) && $args['_no_use_category_filter'])) {
                    $category_arg = $value;
                    $category_set_id = $cf->cf_related_cat_set_id;
                    require_once("MTUtil.php");
                    if (!preg_match('/\b(AND|OR|NOT)\b|\(|\)/i', $category_arg)) {
                        $not_clause = false;
                        $cats = cat_path_to_category($category_arg,
                            isset($blog_ctx_arg) ? $blog_ctx_arg : null, 'category', $category_set_id);
                        if (empty($cats)) {
                            return null;
                        } else {
                            $category_arg = '';
                            foreach ($cats as $cat) {
                                if ($category_arg != '')
                                    $category_arg .= ' OR ';
                                $category_arg .= '#' . $cat->category_id;
                            }
                        }
                    } else {
                        $not_clause = preg_match('/\bNOT\b/i', $category_arg);
                        if ($blog_ctx_arg)
                            $cats = $this->fetch_categories(array_merge($blog_ctx_arg, array('show_empty' => 1, 'class' => 'category', 'category_set_id' => $category_set_id)));
                        else
                            $cats = $this->fetch_categories(array('blog_id' => $blog_id, 'show_empty' => 1, 'class' => 'category', 'category_set_id' => $category_set_id));
                    }

                    if (!empty($cats)) {
                        $cexpr = create_cat_expr_function($category_arg, $cats, 'cd', array(
                            'children' => isset($args['include_subcategories']) ? $args['include_subcategories'] : null,
                            'content_type' => 1
                        ));
                        if ($cexpr) {
                            $cmap = array();
                            $cat_list = array();
                            foreach ($cats as $cat)
                                $cat_list[] = $cat->category_id;
                            $ol = $this->fetch_objectcategory(array('category_id' => $cat_list, 'cf_id' => $cf->cf_id));
                            if (!empty($ol)) {
                                foreach ($ol as $o) {
                                    $oid = $o->objectcategory_object_id;
                                    if (!isset($cmap[$oid][$o->objectcategory_category_id])) {
                                        $cmap[$oid][$o->objectcategory_category_id] = 0;
                                    }
                                    $cmap[$oid][$o->objectcategory_category_id]++;
                                    if (!$not_clause)
                                        $content_list[$oid] = 1;
                                }
                            }
                            $filter_ctx = array();
                            $filter_ctx['c'] = $cmap;
                            $filters[] = array($cexpr, $filter_ctx);
                        } else {
                            return null;
                        }
                    }
                }
                elseif ($type === 'tags') {
                    $tag_arg = $value;
                    require_once("MTUtil.php");
                    $not_clause = preg_match('/\bNOT\b/i', $tag_arg);

                    $include_private = 0;
                    $tag_array = tag_split($tag_arg);
                    foreach ($tag_array as $tag) {
                        $tag_body = trim(preg_replace('/\bNOT\b/i','',$tag));
                        if ($tag_body && (substr($tag_body,0,1) == '@')) {
                            $include_private = 1;
                        }
                    }
                    if (isset($blog_ctx_arg))
                        $tags = $this->fetch_content_tags(array_merge($blog_ctx_arg, array('tag' => $tag_arg, 'include_private' => $include_private)));
                    else
                        $tags = $this->fetch_content_tags(array('blog_id' => $blog_id, 'tag' => $tag_arg, 'include_private' => $include_private));
                    if (!is_array($tags)) $tags = array();
                    $cexpr = create_tag_expr_function($tag_arg, $tags, 'cd');

                    if ($cexpr) {
                        $tmap = array();
                        $tag_list = array();
                        foreach ($tags as $tag) {
                            $tag_list[] = $tag->tag_id;
                        }
                        if (isset($blog_ctx_arg))
                            $ot = $this->fetch_objecttags(array_merge($blog_ctx_arg, array('tag_id' => $tag_list, 'datasource' => 'content_data')));
                        else
                            $ot = $this->fetch_objecttags(array('tag_id' => $tag_list, 'datasource' => 'content_data', 'blog_id' => $blog_id));

                        if ($ot) {
                            foreach ($ot as $o) {
                                $oid = $o->objecttag_object_id;
                                if (!isset($tmap[$oid][$o->objecttag_tag_id])) {
                                    $tmap[$oid][$o->objecttag_tag_id] = 0;
                                }
                                $tmap[$oid][$o->objecttag_tag_id]++;
                                if (!$not_clause)
                                    $cd_list[$oid] = 1;
                            }
                        }
                        $filter_ctx = array();
                        $filter_ctx['t'] = $tmap;
                        $filters[] = array($cexpr, $filter_ctx);;
                    } else {
                        return null;
                    }
                }
                else {
                    $alias = 'cf_idx_' . $cf->id;

                    require_once "content_field_type_lib.php";
                    $cf_type = ContentFieldTypeFactory::get_type($type);
                    $data_type = $cf_type->get_data_type();

                    $join_table = "mt_cf_idx $alias";
                    $join_condition = "$alias.cf_idx_content_field_id = " . $cf->cf_id .
                                      " and $alias.cf_idx_content_data_id = cd_id";
                    $extras['join'][$join_table] = array('condition' => $join_condition);

                    $ph = $this->ph($alias.'_cf_idx_value_'. $data_type, $bind_field_filter, $value);
                    if ($data_type == 'text') {
                        $field = "$alias.cf_idx_value_$data_type";
                        $field = $this->decorate_column($field);
                        $field_filter .= " and $field = ". $ph. "\n";
                    } else {
                        $field_filter .= " and $alias.cf_idx_value_$data_type = ". $ph. "\n";
                    }
                }
            }
        }

        if (count($filters) || !is_null($total_count)) {
            $post_select_limit = isset($limit) ? $limit : null;
            $post_select_offset = isset($offset) ? $offset : null;
            $limit = 0; $offset = 0;
        }

        if (isset($extras['join'])) {
            $joins = $extras['join'];
            $keys = array_keys($joins);
            $bind_join_clause = [];
            foreach($keys as $key) {
                $table = $key;
                $cond = $joins[$key]['condition'];
                $type = '';
                if (isset($joins[$key]['type']))
                    $type = $joins[$key]['type'];
                $join_clause .= ' ' . strtolower($type) . ' JOIN ' . $table . ' ON ' . $cond;
                $bind_join_clause = array_merge($bind_join_clause, isset($joins[$key]['bind']) ? $joins[$key]['bind'] : []);
            }
        }

        if (isset($args['unique_id'])) {
            $unique_id_filter = 'and cd_unique_id = '.
                                                $this->ph('cd_unique_id', $bind_unique_id_filter, $args['unique_id']);
        }

        $bind = array_merge(
            isset($bind_join_clause) ? $bind_join_clause : [],
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_content_type_filter) ? $bind_content_type_filter : [],
            isset($bind_content_filter) ? $bind_content_filter : [],
            isset($bind_author_filter) ? $bind_author_filter : [],
            isset($bind_field_filter) ? $bind_field_filter : [],
            isset($bind_unique_id_filter) ? $bind_unique_id_filter : []
        );

        $sql = implode(' ', array(
            'select mt_cd.* from mt_cd', $join_clause, 'where cd_status = 2',
            isset($blog_filter) ? $blog_filter : '',
            isset($content_type_filter) ? $content_type_filter : '',
            isset($content_filter) ? $content_filter : '',
            isset($author_filter) ? $author_filter : '',
            isset($date_filter) ? $date_filter : '',
            isset($day_filter) ? $day_filter : '',
            isset($field_filter) ? $field_filter : '',
            isset($unique_id_filter) ? $unique_id_filter : ''
        ));
        if ($sort_field) {
            $sql .= " order by $sort_field $base_order";
            if ($sort_field == 'cd_authored_on') {
                $sql .= ",cd_id $base_order";
            }
        }

        if ( !is_null($total_count) ) {
            $orig_offset = $post_select_offset ? $post_select_offset : $offset;
            $orig_limit = $post_select_limit ? $post_select_limit : $limit;
        }

        if (empty($limit) || $limit <= 0) $limit = -1;
        if (empty($offset) || $offset <= 0) $offset = -1;
        $result = $this->SelectLimit($sql, $limit, $offset, $bind);
        if (!$result || $result->EOF) return null;

        $field_names = array_keys($result->fields);

        $contents = array();
        $j = 0;
        $offset = isset($post_select_offset) ? $post_select_offset : (isset($orig_offset) ? $orig_offset : null);
        $limit = isset($post_select_limit) ? $post_select_limit : 0;
        $id_list = array();
        $_total_count = 0;
        $_seen = array();
        while (!$result->EOF) {
            $cd = new ContentData;
            foreach($field_names as $key) {
                if (is_numeric($key)) continue;
  	            $key = strtolower($key);
                $cd->$key = $result->fields($key);
            }
            $result->MoveNext();

            if (empty($cd)) break;
            if (count($filters)) {
                foreach ($filters as $f) {
                    $func = $f[0];
                    $filter_ctx = $f[1];
                    if (!$func($cd, $filter_ctx)) {
                        continue 2;
                    }
                }
            }

            if (array_key_exists($cd->id, $_seen)) continue;
            $_seen[$cd->id] = 1;

            $_total_count++;
            if ( !is_null($total_count) ) {
                if ( ($orig_limit > 0)
                  && ( ($_total_count-$offset) > $orig_limit) ) {
                    // collected all the entries; only count numbers;
                    continue;
                }
            }
            if ($offset && ($j++ < $offset)) continue;
            $cd->cd_authored_on = $this->db2ts($cd->cd_authored_on);
            $cd->cd_modified_on = $this->db2ts($cd->cd_modified_on);
            $id_list[] = $cd->cd_id;
            $contents[] = $cd;
            $this->_comment_count_cache[$cd->cd_id] = $cd->cd_comment_count;
            $this->_ping_count_cache[$cd->cd_id] = $cd->cd_ping_count;
            if ( is_null($total_count) ) {
                // the request does not want total count; break early
                if (($limit > 0) && (count($contents) >= $limit)) break;
            }
        }
        ContentData::bulk_load_meta($contents);

        if ( !is_null($total_count) )
            $total_count = $_total_count;

        if (empty($no_resort)) {
            $sort_field = '';
            if (isset($args['sort_by'])) {
                // TODO: This if-block is totaly wrong where field prefix should be cd_ instead of entry_
                if ($args['sort_by'] == 'title') {
                    $sort_field = 'entry_title';
                } elseif ($args['sort_by'] == 'id') {
                    $sort_field = 'entry_id';
                } elseif ($args['sort_by'] == 'status') {
                    $sort_field = 'entry_status';
                } elseif ($args['sort_by'] == 'modified_on') {
                    $sort_field = 'entry_modified_on';
                } elseif ($args['sort_by'] == 'author_id') {
                    $sort_field = 'entry_author_id';
                } elseif ($args['sort_by'] == 'excerpt') {
                    $sort_field = 'entry_excerpt';
                } elseif ($args['sort_by'] == 'comment_created_on') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'score') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'rate') {
                    $sort_field = $args['sort_by'];
                } elseif ($args['sort_by'] == 'trackback_count') {
                    $sort_field = 'entry_ping_count';  
                } elseif (preg_match('/^field[:\.](.+)$/', $args['sort_by'], $match)) {
                    $sort_field = 'entry_field.' . $match[1];
                } else {
                    if (preg_match('/[^a-zA-Z0-9_]/', $args['sort_by'])) {
                        throw new MTDBException('illegal sort_by name');
                    }
                    $sort_field = 'entry_' . $args['sort_by'];
                }
            } else {
                $sort_field = 'cd_authored_on';
            }

            if ($sort_field) {
                if ($sort_field == 'cd_authored_on') {
                    // already double-sorted by the DB
                } else {
                    if (preg_match('/^cd_(field\..*)/', $sort_field, $match)) {
                        if (! $content_meta_info) {
                            $content_meta_info = ContentData('cd');
                        }
                        $sort_by_numeric =
                            preg_match('/integer|float/', $content_meta_info[$match[1]]);
                    }
                    else {
                        $sort_by_numeric =
                            ($sort_field == 'cd_status') || ($sort_field == 'cd_author_id') || ($sort_field == 'cd_id')
                            || ($sort_field == 'cd_comment_count') || ($sort_field == 'cd_ping_count');
                    }

                    if ($sort_by_numeric) {
                        $sort_fn = function($a, $b) use ($sort_field) {
                            $f = addslashes($sort_field);
                            if ($a->$f == $b->$f) return 0;
                            return $a->$f < $b->$f ? -1 : 1;
                        };
                    } else {
                        $sort_fn = function($a, $b) {
                            return strcmp($a->$f, $b->$f);
                        };
                    }

                    if ($order == 'asc') {
                        $sorter = function($a, $b) use ($sort_fn) {
                            return $sort_fn($a, $b);
                        };
                    } else {
                        $sorter = function($b, $a) use ($sort_fn) {
                            return $sort_fn($a, $b);
                        };
                    }
                    usort($contents, $sorter);

                    if (isset($post_sort_offset)) {
                        $contents = array_slice($contents, $post_sort_offset, $post_sort_limit);
                    }
                }
            }
        }

        if (count($id_list) <= 30) { # TODO: find a good upper limit
            # pre-cache comment counts and categories for these contents
            $this->cache_categories($id_list);
            $this->cache_permalinks($id_list);
        }

        return $contents;
    }

    public function fetch_next_prev_content($direction, $args) {
        $bind = [];
        require_once('class.mt_content_data.php');
        $mt = MT::get_instance();
        $ctx = $mt->context();
        $obj = $ctx->stash('content');
        if( $direction !== 'next' && $direction !== 'previous' )
            return undef;
        $next = $direction === 'next' ? 1 : 0;

        $blog_id         = $obj->blog_id;
        $content_type_id = $obj->content_type_id;

        if (isset($args['by_author'])) {
            $author_id = $obj->author_id;
        }

        if ( isset($args['category_field']) && ($arg = $args['category_field']) ) {
            if (preg_match('/^[0-9]+$/', $arg))
                $cf = $this->fetch_content_field($arg);
            if (!isset($cf)) {
                $cfs = $this->fetch_content_fields(array('unique_id' => $arg));
                if (!isset($cfs))
                    $cfs = $this->fetch_content_fields(array('name' => $arg, 'content_type_id' => $content_type_id));
                if (isset($cfs)) $cf = $cfs[0];
            }
            if (isset($cf)) $cat_field_id = $cf->id;
            $obj_cats = $this->fetch_objectcategories(['object_id' => $obj->id, 'category_field_id' => $cat_field_id]) ?? [];
            foreach ($obj_cats as $obj_cat) {
                if ($obj_cat->is_primary)
                    $category_id = $obj_cat->category_id;
            }
        }

        if ( isset($args['date_field']) && ($arg = $args['date_field']) ) {
            if (   $arg === 'authored_on'
                || $arg === 'modified_on'
                || $arg === 'created_on' )
            {
                $by = $arg;
            }
            else {
                if (preg_match('/^[0-9]+$/', $arg))
                    $cf = $this->fetch_content_field($arg);
                if (!isset($cf)) {
                    $cfs = $this->fetch_content_fields(array('unique_id' => $arg));
                    if (!isset($cfs))
                        $cfs = $this->fetch_content_fields(array('name' => $arg, 'content_type_id' => $content_type_id));
                    if (isset($cfs)) $cf = $cfs[0];
                }
                if (isset($cf)) $dt_field_id = $cf->id;
            }
        }
        else {
            $map = $this->fetch_templatemap(array(
                'type'         => 'ContentType',
                'preferred'    => 1,
                'content_type' => $obj->id
            ));
            if (!empty($maps) && is_array($maps))
                $dt_field_id = $map[0]->dt_field_id;
        }
        if (isset($dt_field_id)) {
            $data = $obj->data();
            $date_field_value = $this->ts2db($data[$dt_field_id]);
        }

        $label = '__' . $direction;
        if (isset($author_id))
            $label .= ':author=' . $author_id;
        if (isset($by))
            $label .= ':by_' . $by;
        if (isset($cat_field_id)) {
            $label .= ":category_field_id=$cat_field_id:category_id=". ($category_id ?? '');
        }
        if (isset($dt_field_id))
            $label .= ":date_field_id=$dt_field_id";
        if (isset($obj->$label))
            return $obj->$label;

        $args = Array();

        $joins = '';
        if (!empty($cat_field_id)) {
            if (isset($category_id)) {
                $joins .= "join mt_cf_idx cat_cf_idx";
                $joins .= " on cat_cf_idx.cf_idx_content_data_id = cd_id";
                $joins .= " and cat_cf_idx.cf_idx_content_field_id = ".
                            $this->ph('cf_idx_content_field_id1', $bind, $cat_field_id);
                $joins .= " and cat_cf_idx.cf_idx_value_integer = ".
                            $this->ph('cf_idx_value_integer', $bind, $category_id);
            }
            else {
                $joins .= "left join mt_cf_idx cat_cf_idx";
                $joins .= " on cat_cf_idx.cf_idx_content_data_id = cd_id";
                $joins .= " and cat_cf_idx.cf_idx_content_field_id = ".
                            $this->ph('cf_idx_content_field_id1', $bind, $cat_field_id);
                $joins .= " and cat_cf_idx.cf_idx_value_integer IS NULL";
            }
        }

        if (!empty($dt_field_id)) {
            $desc = $next ? 'ASC' : 'DESC';
            $op   = $next ? '>'   : '<';

            if (!empty($joins)) $joins .= ' ';
            $joins .= "join mt_cf_idx dt_cf_idx";
            $joins .= " on dt_cf_idx.cf_idx_content_data_id = cd_id";
            $joins .= " and dt_cf_idx.cf_idx_content_field_id = ".
                        $this->ph('cf_idx_content_field_id2', $bind, $dt_field_id);
            $joins .= " and dt_cf_idx.cf_idx_value_datetime $op ".
                        $this->ph('cf_idx_value_datetime', $bind, $date_field_value);
            $order_by = "order by dt_cf_idx.cf_idx_value_datetime $desc, dt_cf_idx.cf_idx_id $desc";
        }

        if (!isset($by)) $by = 'authored_on';

        $cond = [];
        $cond[] = 'cd_blog_id = '. $this->ph('cd_blog_id', $bind, $blog_id);
        $cond[] = 'cd_content_type_id = '. $this->ph('cd_content_type_id', $bind, $content_type_id);
        $cond[] = "cd_status = '2'";

        if (isset($author_id)) {
            $cond[] = 'cd_author_id = '. $this->ph('cd_author_id', $bind, $author_id);
        }

        $sql = "select * from mt_cd $joins where";

        if (!empty($dt_field_id)) {
            $result = $this->SelectLimit($sql. ' '. implode(' and ', $cond). " $order_by", 1, false, $bind);
            if (!$result || $result->EOF) return null;
        }
        else {
            $desc = $next ? 'ASC' : 'DESC';
            $op   = $next ? '>'   : '<';
            $by_value = $this->db2ts($obj->$by);
            $id       = $obj->id;

            $sql_try1 = implode(' ', [
                $sql,
                implode(' and ', array_merge($cond, ["cd_$by $op '$by_value'"])),
                "order by cd_$by $desc, cd_id $desc"
            ]);
            $result = $this->SelectLimit($sql_try1, 1, false, $bind);

            if (!$result || $result->EOF) {
                $sql_try2 = implode(' ', [
                    $sql,
                    implode(' and ', array_merge($cond, ["cd_$by = '$by_value'", "cd_id $op $id"])),
                    "order by cd_$by $desc, cd_id $desc"
                ]);
                $result = $this->SelectLimit($sql_try2, 1, false, $bind);
                if (!$result || $result->EOF) return null;
            }
        }

        $field_names = array_keys($result->fields);
        $contents    = array();

        while (!$result->EOF) {
            $cd = new ContentData;
            foreach($field_names as $key) {
                if (is_numeric($key)) continue;
  	            $key = strtolower($key);
                $cd->$key = $result->fields($key);
            }
            $result->MoveNext();

            if (empty($cd)) break;

            $cd->cd_authored_on = $this->db2ts($cd->cd_authored_on);
            $cd->cd_modified_on = $this->db2ts($cd->cd_modified_on);
            $contents[] = $cd;
        }
        ContentData::bulk_load_meta($contents);

        if (isset($contents)) $obj->$label = $contents[0];

        return $contents[0];
    }

    function fetch_content_field($id) {
        if ( isset( $this->_content_field_id_cache[$id] ) && !empty( $this->_content_field_id_cache[$id] ) ) {
            return $this->_content_field_id_cache[$id];
        }
        require_once("class.mt_content_field.php");
        $content_field= New ContentField;
        $content_field->LoadByIntId($id);
        if ( !empty( $content_field ) ) {
            $this->_content_field_id_cache[$id] = $content_field;
            return $content_field;
        } else {
            return null;
        }
    }

    public function fetch_content_fields($args) {
        if (isset($args['unique_id'])) {
            $unique_id_filter = 'and cf_unique_id = '.
                $this->ph('cf_unique_id', $bind_unique_filter, $args['unique_id']);
        } else {
            if (isset($args['content_type_id'])) {
                if (is_array($args['content_type_id'])) {
                    $ph = $this->in_ph('cf_content_type_id', $bind_content_type_id_filter, $args['content_type_id']);
                    $content_type_id_filter = ' and cf_content_type_id IN ('. $ph. ')';
                } else {
                    $content_type_id_filter = 'and cf_content_type_id = '.
                        $this->ph('cf_content_type_id', $bind_content_type_id_filter, $args['content_type_id']);
                }
            }

            if (isset($args['blog_id'])) {
                $blog_id = $args['blog_id'];
            }
            else {
                $mt = MT::get_instance();
                $ctx = $mt->context();
                $blog = $ctx->stash('blog');
                if ( !empty( $blog ) )
                    $blog_id = $blog->blog_id;
            }
            if (isset($blog_id)) {
                $blog_filter = "and cf_blog_id = ". $this->ph('cf_blog_id', $bind_blog_filter, $blog_id);
            }

            if (!isset($blog_id) && !isset($args['content_type_id'])) return null;

            if (isset($args['name'])) {
                $name_filter = 'and cf_name = '. $this->ph('cf_name', $bind_name_filter, $args['name']);
            }
            if (isset($args['related_cat_set_id'])) {
                $related_cat_set_id_filter = 'and cf_related_cat_set_id = '.
                    $this->ph('cf_related_cat_set_id', $bind_related_cat_set_id_filter, $args['related_cat_set_id']);
            }
        }
        $bind = array_merge(
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_content_type_id_filter) ? $bind_content_type_id_filter : [],
            isset($bind_name_filter) ? $bind_name_filter : [],
            isset($bind_unique_filter) ? $bind_unique_filter : [],
            isset($bind_related_cat_set_id_filter) ? $bind_related_cat_set_id_filter : []
        );
        $sql = implode(' ', array(
            "select * from mt_cf",
            "where 1 = 1",
            isset($blog_filter) ? $blog_filter : '',
            isset($content_type_id_filter) ? $content_type_id_filter : '',
            isset($name_filter) ? $name_filter : '',
            isset($unique_id_filter) ? $unique_id_filter : '',
            isset($related_cat_set_id_filter) ? $related_cat_set_id_filter : ''
        ));
        $result = $this->SelectLimit($sql, -1, -1, $bind);
        if ($result->EOF) return null;

        $field_names = array_keys($result->fields);

        $content_fields = array();
        while (!$result->EOF) {
            require_once('class.mt_content_field.php');
            $cf = new ContentField;
            foreach($field_names as $key) {
                if (is_numeric($key)) continue;
      	        $key = strtolower($key);
                $cf->$key = $result->fields($key);
            }
            $result->MoveNext();

            if (empty($cf)) break;

            $cf->cf_modified_on = $this->db2ts($cf->cf_modified_on);
            $content_fields[] = $cf;
        }

        return $content_fields;
    }

    public function fetch_objectcategory($args) {

        $bind = [];

        if (isset($args['category_id'])) {
            $id_list = $this->in_ph('objectcategory_category_id', $bind, $args['category_id']);
        }
        if (empty($id_list)) {
            return;
        }

        $blog_filter = 'and objectcategory_blog_id '.
                                                $this->_include_exclude_blogs('objectcategory_blog_id', $bind, $args);

        $cf_filter = '';
        if (isset($args['cf_id']) && is_numeric($args['cf_id'])) {
            $cf_filter = 'and objectcategory_cf_id = '. $this->ph('objectcategory_cf_id', $bind, $args['cf_id']);
        }

        $extras = array();
        $datasource = 'content_data';
        $extras['join'] = array(
            'mt_cd' => array(
                'condition' => 'cd_id = objectcategory_object_id'
                )
            );
        $object_filter = 'and cd_status = 2';

        require_once('class.mt_objectcategory.php');
        $ocat = new ObjectCategory;

        $where = "objectcategory_object_ds = '$datasource'
                and objectcategory_category_id in ($id_list)
                $blog_filter
                $cf_filter
                $object_filter";

        return $ocat->Find($where, $bind, false, $extras);
    }

    public function fetch_objectcategories($args) {
        if (isset($args['object_id']))
            $object_id = $args['object_id'];
        else
            return undef;

        if (isset($args['category_field_id']))
            $category_field_id = $args['category_field_id'];
        else
            return undef;

        require_once('class.mt_objectcategory.php');
        $ocat = new ObjectCategory;

        $where = sprintf(
            "objectcategory_cf_id = %s
                                and objectcategory_object_ds = 'content_data'
                                and objectcategory_object_id = %s",
            $this->ph('objectcategory_cf_id', $bind, $category_field_id),
            $this->ph('objectcategory_object_id', $bind, $object_id)
        );

        return $ocat = $ocat->Find($where, $bind);
    }

    public function fetch_content_tags($args) {
        # load tags

        $class = 'content_data';
        $cacheable 
            = empty( $args['tags'] )
            && empty( $args['tag'] )
            && empty( $args['include_private'] );

        if (isset($args['cd_id'])) {
            if ($cacheable) {
                if (isset($this->_cd_tag_cache[$args['cd_id']])) {
                    return $this->_cd_tag_cache[$args['cd_id']];
                }
            }
            $cd_filter = sprintf(
                'and objecttag_tag_id in (select objecttag_tag_id from mt_objecttag where objecttag_object_id=%s)',
                $this->ph('objecttag_object_id', $bind_cd_filter, intval($args['cd_id']))
            );
        }

        $blog_filter = 'and objecttag_blog_id ' . 
                                        $this->_include_exclude_blogs('objecttag_blog_id', $bind_blog_filter, $args);

        $ct_filter = '';
        if (isset($args['content_type_id'])) {
            if (is_array($args['content_type_id'])) {
                $ph = $this->in_ph('cd_content_type_id', $bind_ct_filter, $args['content_type_id']);
                $ct_filter = 'and cd_content_type_id in (' . $ph. ')';
            } else {
                $ct_filter = 'and cd_content_type_id = '.
                        $this->ph('cd_content_type_id', $bind_ct_filter, $args['content_type_id']);
            }
        }

        if (empty($args['include_private'])) {
            $private_filter = 'and (tag_is_private = 0 or tag_is_private is null)';
        }
        if (! empty($args['tags'])) {
            $tag_list = '';
            require_once("MTUtil.php");
            $tag_array = tag_split($args['tags']);
            if (!empty($tag_array)) {
                $ph = $this->in_ph('tag_name', $bind_tag_filter, $tag_array);
                if (!empty($ph)) {
                    $tag_filter = 'and (tag_name in (' . $ph . '))';
                    $private_filter = '';
                }
            }
        }

        $sort_col = isset($args['sort_by']) ? $args['sort_by'] : 'name';
        $sort_col = "tag_$sort_col";
        if (isset($args['sort_order']) and $args['sort_order'] == 'descend') {
            $order = 'desc';
        } else {
            $order = 'asc';
        }
        $id_order = '';
        if ($sort_col == 'tag_name' || $sort_col == 'name') {
            $sort_col = 'lower(tag_name)';
        }else{
            $id_order = ', lower(tag_name)';
        }

        $bind = array_merge(
            isset($bind_blog_filter) ? $bind_blog_filter : [],
            isset($bind_tag_filter) ? $bind_tag_filter : [],
            isset($bind_cd_filter) ? $bind_cd_filter : [],
            isset($bind_ct_filter) ? $bind_ct_filter : []
        );
        $sql = implode(' ', array(
            'select tag_id, tag_name, count(*) as tag_count',
            'from mt_tag, mt_objecttag, mt_cd',
            'where objecttag_tag_id = tag_id',
            "and cd_id = objecttag_object_id and objecttag_object_datasource='content_data'",
            'and cd_status = 2',
            isset($blog_filter) ? $blog_filter : '',
            isset($tag_filter) ? $tag_filter : '',
            isset($cd_filter) ? $cd_filter : '',
            isset($ct_filter) ? $ct_filter : '',
            isset($private_filter) ? $private_filter : '',
            "group by tag_id, tag_name",
            "order by $sort_col $order $id_order, tag_id desc"
        ));
        $rs = $this->SelectLimit($sql, -1, -1, $bind);

        require_once('class.mt_tag.php');
        $tags = array();
        while(!$rs->EOF) {
            $tag = new Tag;
            $tag->tag_id = $rs->Fields('tag_id');
            $tag->tag_name = $rs->Fields('tag_name');
            $tag->tag_count = $rs->Fields('tag_count');
            $tags[] = $tag;
            $rs->MoveNext();
        }
        if ($cacheable) {
            if (!empty($args['cd_id']))
                $this->_cd_tag_cache[$args['cd_id']] = $tags;
            elseif (empty($args['cd_id']))
                $this->_blog_tag_cache[$args['blog_id'].":$class"] = $tags;
        }
        return $tags;
    }
    public function fetch_content_type_id($args){
        require_once('class.mt_content_type.php');

        $ct = new ContentType;
        if (isset($args['content_type'])) {
            $str = $args['content_type'];
            if (ctype_digit($str)) {
                $ct->LoadByIntId($str);
            }
            if (is_null($ct->id)) {
                $ct->Load('content_type_unique_id = '. $this->ph('content_type_unique_id', $bind, $str), $bind);
                if (is_null($ct->id)) {
                    $cond = [];
                    $cond[] = 'content_type_name = '. $this->ph('content_type_name', $bind2, $str);
                    if (isset($args['blog_id'])) {
                        $cond[] = 'content_type_blog_id = '.
                            $this->ph('content_type_blog_id', $bind2, intval($args['blog_id']));
                    }
                    $ct->Load(implode(' and ', $cond), $bind2);
                }
                if (is_null($ct->id)) return null;
            }
        }
        return $ct->id;
    }
    
    public function content_count($args){
        $bind = [];
        $blog_filter = 'and cd_blog_id ' . $this->_include_exclude_blogs('cd_blog_id', $bind, $args);
        $where = "cd_status = 2
                  $blog_filter";
        if (isset($args['content_type'])) {
            $content_types = $this->fetch_content_types($args);
            if ($content_types) {
                $where .= ' and cd_content_type_id = ' . $this->ph('cd_content_type_id', $bind, $content_types[0]->id);
            }
        }

        require_once('class.mt_content_data.php');
        $ct = new ContentData();
        $count = $ct->count(['where' => $where, 'bind' => $bind]);
        return $count;
    }

    public function content_link($cid, $at = "ContentType", $args = null) {
        $cid = intval($cid);
        $cache_id = $cid.';'.$at. ';'. (!empty($args['with_index']) ? 1 : 0);
        if (isset($this->_content_link_cache[$cache_id])) {
            $url = $this->_content_link_cache[$cache_id];
        } else {
            $extras['join'] = array(
                'mt_templatemap' => array(
                    'condition' => "templatemap_id = fileinfo_templatemap_id"
                    )
                );
            $filter = '';

            $bind_filter = [];

            if (preg_match('/Category/', $at)) {
                $extras['join']['mt_objectcategory'] = array(
                    'condition' => "fileinfo_category_id = objectcategory_category_id"
                );
                $filter = " and objectcategory_object_ds = 'content_data'";
                $filter .= ' and objectcategory_object_id = '.
                        $this->ph('objectcategory_object_id', $bind_filter, $cid);
                $filter .= " and objectcategory_is_primary = 1";
            }

            $content = $this->fetch_content($cid);

            $ts = $this->db2ts($content->authored_on);
            if ($ts === '') {
                // DO NOTHING
            } elseif (preg_match('/Monthly$/', $at)) {
                $ts = substr($ts, 0, 6) . '01000000';
            } elseif (preg_match('/Daily$/', $at)) {
                $ts = substr($ts, 0, 8) . '000000';
            } elseif (preg_match('/Weekly$/', $at)) {
                require_once("MTUtil.php");
                list($ws, $we) = start_end_week($ts);
                $ts = $ws;
            } elseif (preg_match('/Yearly$/', $at)) {
                $ts = substr($ts, 0, 4) . '0101000000';
            } elseif ($at == 'ContentType') {
                $filter .= " and fileinfo_cd_id = ". $this->ph('fileinfo_cd_id', $bind_filter, $cid);
            }
            if (preg_match('/(Monthly|Daily|Weekly|Yearly)$/', $at)) {
                $filter .= ' and fileinfo_startdate = '. $this->ph('fileinfo_startdate', $bind_filter, $ts);
            }
            if (preg_match('/Author/', $at)) {
                $filter .= ' and fileinfo_author_id = '.
                    $this->ph('fileinfo_author_id', $bind_filter, $content->author_id);
            }
            $where = 'templatemap_archive_type = '. $this->ph('templatemap_archive_type', $bind, $at);
            $where .= ' and templatemap_is_preferred = 1 '. $filter;
            $bind = array_merge($bind, $bind_filter ?? []);
            if (isset($args['blog_id'])) {
                $where .= ' and fileinfo_blog_id = '. $this->ph('fileinfo_blog_id', $bind, $args['blog_id']);
            }
            require_once('class.mt_fileinfo.php');
            $finfo = new FileInfo;
            $infos = $finfo->Find($where, $bind, false, $extras);
            if (empty($infos))
                return null;

            $finfo = $infos[0];
            $blog = $finfo->blog();
            $blog_url = $blog->archive_url();
            if (empty($blog_url))
                $blog_url = $blog->site_url();

            require_once('MTUtil.php');
            if(preg_match('/https?/',$blog_url)){
                $blog_url = preg_replace('!(https?://(?:[^/]+))/.*!', '$1', $blog_url);
                $url = caturl(array($blog_url, $finfo->fileinfo_url));
            } else {
                $url = $finfo->fileinfo_url;
            }
            if(!isset($args['with_index']) || !$args['with_index'] ){
                $url = _strip_index($url, $blog);
            }
            $this->_content_link_cache[$cache_id] = $url;
        }

        if ( $at != 'ContentType' && (!$args || !isset($args['no_anchor'])) ) {
            $url .= '#' . (!$args || isset($args['valid_html']) ? 'a' : '') .
                    sprintf("%06d", $cid);
        }

        return $url;
    }

    public function flush_cache() {
        $this->_blog_id_cache = array();
    }

    public function in_ph($name, &$bind, $values) {
        $ph = array();
        foreach($values as $i => $v) {
            if (is_null($v)) continue;
            $ph[] = $this->conn->param($name. $i);
            $bind[$name. $i] = $values[$i];
        }
        return join(',', $ph);
    }

    public function ph($name, &$bind, $value) {
        $bind[$name] = $value;
        return $this->conn->param($name);
    }
}
?>
