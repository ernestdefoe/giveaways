import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Icon from 'flarum/common/components/Icon';

import { listCategories, saveCategory, deleteCategory } from '../../common/api';
import type { GiveawayCategory } from '../../common/api';

export interface CategoryManagerAttrs extends IInternalModalAttrs {
  onchange?: () => void;
}

export default class CategoryManagerModal extends Modal<CategoryManagerAttrs> {
  loading = true;
  categories: GiveawayCategory[] = [];
  newName = '';
  newColor = '#69c6b9';
  newIcon = '';

  oninit(vnode: Mithril.Vnode<CategoryManagerAttrs>) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    this.loading = true;
    listCategories().then((res) => {
      this.categories = res.data || [];
      this.loading = false;
      m.redraw();
    });
  }

  className() {
    return 'CategoryManagerModal Modal--medium';
  }

  title() {
    return app.translator.trans('ernestdefoe-giveaways.forum.categories.manage_title');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        {this.loading ? (
          <LoadingIndicator />
        ) : (
          <div className="CategoryManager">
            <ul className="CategoryManager-list">
              {this.categories.map((c) => (
                <li className="CategoryManager-row" key={c.id}>
                  <input
                    type="color"
                    className="CategoryManager-color"
                    value={c.color}
                    oninput={(e: Event) => { c.color = (e.target as HTMLInputElement).value; }}
                  />
                  <input
                    className="FormControl CategoryManager-name"
                    value={c.name}
                    oninput={(e: Event) => { c.name = (e.target as HTMLInputElement).value; }}
                  />
                  <span className="CategoryManager-iconPreview" style={{ color: c.color }} aria-hidden="true">
                    <Icon name={c.icon || 'fas fa-tag'} />
                  </span>
                  <input
                    className="FormControl CategoryManager-icon"
                    placeholder="fas fa-tag"
                    value={c.icon || ''}
                    oninput={(e: Event) => { c.icon = (e.target as HTMLInputElement).value; }}
                  />
                  <span className="CategoryManager-count">{c.count ?? 0}</span>
                  <Button
                    className="Button Button--icon Button--flat"
                    icon="fas fa-save"
                    title={app.translator.trans('ernestdefoe-giveaways.forum.form.submit')}
                    onclick={() => this.save(c)}
                  />
                  <Button
                    className="Button Button--icon Button--flat CategoryManager-delete"
                    icon="fas fa-trash"
                    title={app.translator.trans('ernestdefoe-giveaways.forum.delete')}
                    onclick={() => this.remove(c)}
                  />
                </li>
              ))}
              {this.categories.length === 0 && (
                <li className="CategoryManager-empty">
                  {app.translator.trans('ernestdefoe-giveaways.forum.categories.none')}
                </li>
              )}
            </ul>

            <div className="CategoryManager-add">
              <h4>
                <Icon name="fas fa-plus" /> {app.translator.trans('ernestdefoe-giveaways.forum.categories.add')}
              </h4>
              <div className="CategoryManager-row">
                <input
                  type="color"
                  className="CategoryManager-color"
                  value={this.newColor}
                  oninput={(e: Event) => { this.newColor = (e.target as HTMLInputElement).value; }}
                />
                <input
                  className="FormControl CategoryManager-name"
                  placeholder={app.translator.trans('ernestdefoe-giveaways.forum.categories.name_placeholder') as string}
                  value={this.newName}
                  oninput={(e: Event) => { this.newName = (e.target as HTMLInputElement).value; }}
                />
                <span className="CategoryManager-iconPreview" style={{ color: this.newColor }} aria-hidden="true">
                  <Icon name={this.newIcon || 'fas fa-tag'} />
                </span>
                <input
                  className="FormControl CategoryManager-icon"
                  placeholder="fas fa-tag"
                  value={this.newIcon}
                  oninput={(e: Event) => { this.newIcon = (e.target as HTMLInputElement).value; }}
                />
                <Button className="Button Button--primary" icon="fas fa-plus" loading={this.loading} onclick={() => this.add()}>
                  {app.translator.trans('ernestdefoe-giveaways.forum.categories.add')}
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  add() {
    if (!this.newName.trim()) return;
    this.loading = true;
    saveCategory({ name: this.newName, color: this.newColor, icon: this.newIcon })
      .then(() => {
        this.newName = '';
        this.newIcon = '';
        this.attrs.onchange?.();
        this.load();
      })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  save(c: GiveawayCategory) {
    saveCategory({ name: c.name, color: c.color, icon: c.icon }, c.id).then(() => {
      this.attrs.onchange?.();
      app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.saved'));
    });
  }

  remove(c: GiveawayCategory) {
    if (!confirm(app.translator.trans('ernestdefoe-giveaways.forum.categories.confirm_delete') as string)) return;
    deleteCategory(c.id).then(() => {
      this.attrs.onchange?.();
      this.load();
    });
  }
}
