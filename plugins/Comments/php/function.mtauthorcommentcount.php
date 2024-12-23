<?php
# Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id$

function smarty_function_mtauthorcommentcount($args, &$ctx) {
    $mt = MT::get_instance();
    $author = $ctx->stash('author');

    $db = $mt->db()->db();
    $sql = sprintf('select count(*)
        from mt_comment
        join mt_entry on entry_id = comment_entry_id
        where comment_visible = %s
        and comment_commenter_id = %s
        and entry_status = %s', 
        $db->param('comment_visible'), 
        $db->param('comment_commenter_id'), 
        $db->param('entry_status')
    );
    $conn = $mt->db()->db();
    $handle = $conn->prepare($sql);

    $comment_visible = 1;
    $commenter_id = $author->id;
    $entry_release = 2; # RELEASE
    $bindVars = array(
        'comment_visible'      => $comment_visible,
        'comment_commenter_id' => $commenter_id,
        'entry_status'         => $entry_release
    );

    $row = $conn->getRow($handle, $bindVars);

    return $ctx->count_format($row[0], $args);
}
?>
