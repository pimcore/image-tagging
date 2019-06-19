/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */


pimcore.registerNS("pimcore.bundle.imageTaggingBundle");

pimcore.bundle.imageTaggingBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function() {
        return "pimcore.plugin.imageTaggingBundle";
    },

    initialize: function() {
        pimcore.plugin.broker.registerPlugin(this);
    },

    postOpenAsset: function (asset, type) {

        if(type == 'image') {

            Ext.Ajax.request({
                url: '/admin/bundle/image-tagging/list-models',
                method: 'post',
                params: {},
                success: function (asset, response) {
                    var data = Ext.decode(response.responseText);

                    if (data.success && data.models.length > 0) {

                        var items = [];

                        for(var i = 0; i < data.models.length; i++) {
                            items.push({
                                text: data.models[i].nicename,
                                iconCls: 'image_tagging_model_white',
                                handler: function (asset, model) {
                                    asset.tab.mask();
                                    Ext.Ajax.request({

                                        url: '/admin/bundle/image-tagging/classify',
                                        method: 'post',
                                        params: {
                                            'id': asset.id,
                                            'model': model.name,
                                            'version': model.version
                                        },
                                        success: function (asset, response) {
                                            asset.tab.unmask();
                                            if (data.success) {
                                                asset.tabbar.setActiveItem(asset.tagAssignment.getLayout());
                                                asset.tagAssignment.grid.store.reload();
                                                asset.tagAssignment.layout.items.get(1).items.get(0).store.reload();

                                                pimcore.helpers.showNotification(t("image_tagging_auto_classification"), t("image_tagging_auto_classification_tagging_successful"), "success");
                                            } else {
                                                pimcore.helpers.showNotification(t("error"), t("image_tagging_auto_classification_tagging_failed"), "error");
                                            }
                                        }.bind(this, asset)

                                    });

                                }.bind(this, asset, data.models[i])
                            });

                        }

                        asset.toolbar.add({
                            text: t('image_tagging_auto_classification'),
                            scale: "medium",
                            iconCls: 'image_tagging_white',
                            cls: 'pimcore_workflow_button',
                            menu: {
                                xtype: 'menu',
                                items: items
                            }
                        });
                        pimcore.layout.refresh();

                    } else {
                        Ext.MessageBox.alert(data.message, data.reason);
                    }

                }.bind(this, asset)
            });
        }
    }
});

var imageTaggingBundle = new pimcore.bundle.imageTaggingBundle();

