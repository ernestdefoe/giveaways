import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

const KEY = 'ernestdefoe-giveaways.';

export const extend = [
  new Extend.Admin()
    .setting(() => ({
      setting: KEY + 'show_nav',
      type: 'boolean',
      label: app.translator.trans('ernestdefoe-giveaways.admin.show_nav_setting'),
    }))
    .setting(() => ({
      setting: KEY + 'nav_label',
      type: 'text',
      label: app.translator.trans('ernestdefoe-giveaways.admin.nav_label_setting'),
      help: app.translator.trans('ernestdefoe-giveaways.admin.nav_label_help'),
      placeholder: 'Giveaways',
    }))
    .permission(
      () => ({
        icon: 'fas fa-ticket-alt',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_enter'),
        permission: 'giveaways.enter',
      }),
      'start',
      95
    )
    .permission(
      () => ({
        icon: 'fas fa-gift',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_create'),
        permission: 'giveaways.create',
      }),
      'start',
      90
    )
    .permission(
      () => ({
        icon: 'fas fa-cog',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_manage'),
        permission: 'giveaways.manage',
      }),
      'moderate',
      90
    ),
];
