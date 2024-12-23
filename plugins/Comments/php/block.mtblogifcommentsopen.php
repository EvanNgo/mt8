<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

function smarty_block_mtblogifcommentsopen($args, $content, &$ctx, &$repeat) {
    // status: complete
    // parameters: none
    if (!isset($content)) {
        $switch = $ctx->mt->config('PluginSwitch');
        if (isset($switch) && isset($switch["Comments"]) && !$switch["Comments"]) {
            return $ctx->_hdlr_if($args, $content, $ctx, $repeat, 0);
        }

        $blog = $ctx->stash('blog');
        if ($ctx->mt->config('AllowComments') &&
            (($blog->blog_allow_reg_comments && $blog->remote_auth_token)
             || $blog->blog_allow_unreg_comments))
            $open = 1;
        else
            $open = 0;
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat, $open);
    } else {
        return $ctx->_hdlr_if($args, $content, $ctx, $repeat);
    }
}
?>
