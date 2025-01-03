/*
 * Movable Type (r) (C) Six Apart Ltd. All Rights Reserved.
 * This code cannot be redistributed without permission from www.sixapart.com.
 * For more information, consult your Movable Type license.
 *
 * $Id$
 */
;(function($) {
    var enabled = false;
    var fitToWindow = function(){};
    var editorSize = null;

    tinymce
        .ScriptLoader
        .add(tinymce.PluginManager.urls['mt_fullscreen'] + '/langs/plugin.js');

    // Register plugin
    tinymce.PluginManager.add('mt_fullscreen', function(ed, url) {
        tinymce.DOM.loadCSS(url + '/css/mt_fullscreen.css');

        var $window, $container, $parent, $header, $tabs, affectedEditors;

        function forEachAffectedEditors(func) {
            $.each(affectedEditors, function(i, id) {
                if (tinyMCE.editors[id]) {
                    func.apply(tinyMCE.editors[id], []);
                }
            });
        }

        ed.addCommand('mtFullScreenUpdateFitToWindow', function() {
            if (! enabled) {
                return;
            }
            var header_height = $header.length > 0 ? $header.height() : 0;

            fitToWindow = function() {
                var $outer = $parent.find('.tox-tinymce');
                var $inner_ifr = $parent.find('.tox-tinymce iframe');
                var $inner_text = $parent.find('.tox-tinymce textarea');


                forEachAffectedEditors(function() {
                    $outer.height($window.height() - header_height);
                    $inner_ifr.height('100%');
                    $inner_text.height('100%');
                });
            };
        });

        ed.addCommand('mtFullScreenFitToWindow', function() {
            if (fitToWindow) {
                fitToWindow();
            }
        });

        ed.addCommand('mtFullScreen', function() {
            if (! enabled) {
                editorSize = ed.queryCommandValue('mtGetEditorSize');

                $parent
                    .addClass('fullscreen_editor tox-fullscreen')
                    .css({
                        width: '100%',
                        margin: '0',
                        padding: '0'
                    });
                $('body').addClass('fullscreen_editor_screen tox-fullscreen');

                forEachAffectedEditors(function() {
                    $('.tox-statusbar__resize-handle').hide();
                });

                enabled = true;
                ed.execCommand('mtFullScreenUpdateFitToWindow');
                fitToWindow();
                $window.on('resize.mt_fullscreen', fitToWindow);
            }
            else {
                ed.execCommand('mtRestoreEditorSize', editorSize);

                $parent
                    .removeClass('fullscreen_editor tox-fullscreen')
                    .css({
                        width: '',
                        margin: '',
                        padding: ''
                    });
                $('body').removeClass('fullscreen_editor_screen tox-fullscreen');

                forEachAffectedEditors(function() {
                    $('.tox-statusbar__resize-handle').show();
                });

                enabled = false;
                fitToWindow = function(){};
                $window.off('resize.mt_fullscreen');
                // scroll for editor.
                $window.scrollTop($parent.find('.tox-tinymce').offset().top);
            }
            ed.fire('mtFullscreenStateChanged', {state: enabled});

            forEachAffectedEditors(function() {
                this.nodeChanged();
            });
        });

        ed.addMTButton('mt_fullscreen', {
            icon: 'fullscreen',
            tooltip: 'fullscreen',
            toggle: true,
            onAction: function(){
                return ed.execCommand('mtFullScreen');
            },
            onSetup: function (buttonApi) {
                ed.on('mtFullscreenStateChanged', function (e) {
                buttonApi.setActive(e.state);
                });
            }            
        });

        ed.on('init', function(args) {
            $window     = $(window);
            $container  = $(ed.getContainer());
            $parent     = $container.closest('#text-field');
            $header     = $parent.find('.editor-header');
            $tabs       = $header.find('.tab');
            if ($parent.length == 0 && ( $header.length == 0 || $tabs.length == 0 )) {
                $parent = $container.closest('.mt-contentblock');
            }
            fitToWindow = function(){};

            affectedEditors = $parent
                .find('textarea')
                .map(function() { return this.id });
        });

        return {
            getInfo : function() {
                return {
                    longname : 'MTFullscreen',
                    author : 'Six Apart Ltd',
                    authorurl : '',
                    infourl : '',
                    version : '1.0'
                };
            }
        };
    });
})(jQuery);