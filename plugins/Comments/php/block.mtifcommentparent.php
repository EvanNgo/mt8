<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

function smarty_block_mtifcommentparent($args, $content, &$ctx, &$repeat) {
    if (!isset($content)) {
        $switch = $ctx->mt->config('PluginSwitch');
        if (isset($switch) && isset($switch["Comments"]) && !$switch["Comments"]) {
            return $ctx->_hdlr_if($args, $content, $ctx, $repeat, 0);
        }

        $comment = $ctx->stash('comment');
        $has_parent = 0;
        if ($comment && $comment->comment_parent_id) {
            $parent = $ctx->mt->db()->fetch_comment_parent(array( 'parent_id' => $comment->comment_parent_id, 'blog_id' => $comment->comment_blog_id));
            $has_parent = $parent ? 1 : 0;
        }
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat, $has_parent);
    } else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }
}
?>
