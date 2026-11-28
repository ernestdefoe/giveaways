import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

const KEY = 'ernestdefoe-giveaways.';

export default [
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
      placeholder: app.translator.trans('ernestdefoe-giveaways.admin.nav_label_placeholder', {}, true),
    }))
    .setting(() => ({
      setting: KEY + 'advent_jokers',
      type: 'number',
      min: 0,
      max: 3,
      label: app.translator.trans('ernestdefoe-giveaways.admin.advent_jokers_setting'),
      help: app.translator.trans('ernestdefoe-giveaways.admin.advent_jokers_help'),
    }))
    .setting(() => ({
      setting: KEY + 'sponsor_retention_days',
      type: 'number',
      min: 0,
      max: 365,
      label: app.translator.trans('ernestdefoe-giveaways.admin.sponsor_retention_setting'),
      help: app.translator.trans('ernestdefoe-giveaways.admin.sponsor_retention_help'),
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
        icon: 'fas fa-users',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_view_participants'),
        permission: 'giveaways.viewParticipants',
      }),
      'moderate',
      91
    )
    .permission(
      () => ({
        icon: 'fas fa-cog',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_manage'),
        permission: 'giveaways.manage',
      }),
      'moderate',
      90
    )
    // Downloads members' email addresses: admins only until granted.
    .permission(
      () => ({
        icon: 'fas fa-file-csv',
        label: app.translator.trans('ernestdefoe-giveaways.admin.perm_export_sponsor_optins'),
        permission: 'giveaways.exportSponsorOptIns',
      }),
      'moderate',
      89
    ),
];
