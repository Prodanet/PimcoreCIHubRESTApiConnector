pimcore.registerNS("pimcore.plugin.simpleRestAdapterBundle.user.ciHub");
pimcore.plugin.simpleRestAdapterBundle.user.ciHub = Class.create({
    initialize: function (userPanel) {
        this.userPanel = userPanel;
        this.data = this.userPanel.data;
    },
    getPanel: function () {
        const generateToken = (n) => {
            var chars = '!@#$%^&*()_+{}:"|?><,./;`abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            var token = '';
            for(var i = 0; i < n; i++) {
                token += chars[Math.floor(Math.random() * chars.length)];
            }
            return token;
        };
        const apikeyField = new Ext.form.field.Text({
            xtype: 'textfield',
            labelWidth: 200,
            width: 600,
            fieldLabel: 'Token',
            name: 'apikey',
            value: this.data.cihub ? this.data.cihub.apikey : '',
            minLength: 16,
        });

        this.deliverySettingsForm = new Ext.form.FormPanel({
            bodyStyle: 'padding:10px;',
            autoScroll: true,
            defaults: {
                labelWidth: 200,
            },
            border: false,
            title: t('CI-HUB Settings'),
            items: [
                {
                    xtype: 'fieldcontainer',
                    layout: 'hbox',
                    items: [
                        apikeyField,
                        {
                            xtype: 'button',
                            width: 32,
                            style: 'margin-left: 8px',
                            iconCls: 'pimcore_icon_clear_cache',
                            handler: () => {
                                apikeyField.setValue(generateToken(32));
                            },
                        },
                    ],
                },
            ],
        });

        return this.deliverySettingsForm;
    },
});