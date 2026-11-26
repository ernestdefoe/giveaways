import app from 'flarum/admin/app';
import { registerEditorWidgets } from '../common/fofWidgets';

export { default as extend } from './extend';

// FoF's layout editor lists only widgets the admin registers too.
app.initializers.add('ernestdefoe-giveaways-widgets', () => {
  registerEditorWidgets(app, 'ernestdefoe-giveaways', [
    {
      key: 'giveawaysTodayDoor',
      icon: 'fas fa-door-open',
      title: () => app.translator.trans('ernestdefoe-giveaways.admin.widget_today_door'),
    },
  ]);
});
