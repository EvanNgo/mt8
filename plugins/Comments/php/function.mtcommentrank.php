<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

require_once('rating_lib.php');

function smarty_function_mtcommentrank($args, &$ctx) {
    return hdlr_rank($ctx, 'comment', 
        isset($args['namespace']) ? $args['namespace'] : null,
        isset($args['max']) ? $args['max'] : null,
        "AND (comment_visible = 1)\n", $args
    );
}
?>
