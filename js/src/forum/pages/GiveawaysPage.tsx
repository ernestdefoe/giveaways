import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import GwSkeleton, { rememberSections } from '../components/GwSkeleton';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

import { listGiveaways, listCategories } from '../../common/api';
import type { Giveaway, GiveawayCategory } from '../../common/api';
import GiveawayCard from '../components/GiveawayCard';
import GiveawayFormModal from '../components/GiveawayFormModal';
import CategoryManagerModal from '../components/CategoryManagerModal';

export default class GiveawaysPage extends Page {
  loading = true;
  giveaways: Giveaway[] = [];
  categories: GiveawayCategory[] = [];
  canCreate = false;
  canManage = false;
  filter: number | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(app.translator.trans('ernestdefoe-giveaways.forum.page_title') as string);
    this.load();
    this.loadCategories();
  }

  load() {
    this.loading = true;
    listGiveaways()
      .then((res) => {
        this.giveaways = res.data || [];
        this.canCreate = !!(res.meta && res.meta.canCreate);
        this.canManage = !!(res.meta && res.meta.canManage);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  loadCategories() {
    listCategories().then((res) => {
      this.categories = res.data || [];
      m.redraw();
    });
  }

  create() {
    app.modal.show(GiveawayFormModal, { categories: this.categories, onsave: () => this.load() });
  }

  manageCategories() {
    app.modal.show(CategoryManagerModal, { onchange: () => this.loadCategories() });
  }

  matchesFilter(g: Giveaway): boolean {
    return this.filter === null || (g.category && g.category.id === this.filter) || false;
  }

  view(): Mithril.Children {
    const filtered = this.giveaways.filter((g) => this.matchesFilter(g));
    const active = filtered.filter((g) => g.status === 'active');
    const past = filtered.filter((g) => g.status !== 'active');

    return (
      <div className="GiveawaysPage">
        <div className="GiveawaysPage-hero">
          <div className="container">
            <h1 className="GiveawaysPage-title">
              {app.translator.trans('ernestdefoe-giveaways.forum.heading')}
            </h1>
            <p className="GiveawaysPage-subtitle">
              {app.translator.trans('ernestdefoe-giveaways.forum.subheading')}
            </p>
            <div className="GiveawaysPage-actions">
              {this.canCreate && (
                <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.create()}>
                  {app.translator.trans('ernestdefoe-giveaways.forum.create')}
                </Button>
              )}
              {this.canManage && (
                <Button className="Button" icon="fas fa-tags" onclick={() => this.manageCategories()}>
                  {app.translator.trans('ernestdefoe-giveaways.forum.categories.manage')}
                </Button>
              )}
            </div>
          </div>
        </div>

        <div className="container GiveawaysPage-content">
          {this.categories.length > 0 && (
            <div className="GiveawaysPage-filters">
              <button
                className={'GiveawayFilter' + (this.filter === null ? ' is-active' : '')}
                onclick={() => { this.filter = null; }}
              >
                {app.translator.trans('ernestdefoe-giveaways.forum.categories.all')}
              </button>
              {this.categories.map((c) => (
                <button
                  key={c.id}
                  className={'GiveawayFilter' + (this.filter === c.id ? ' is-active' : '')}
                  style={this.filter === c.id ? { backgroundColor: c.color, borderColor: c.color, color: '#fff' } : { color: c.color, borderColor: c.color }}
                  onclick={() => { this.filter = c.id; }}
                >
                  {c.icon && <Icon name={c.icon} />} {c.name}
                </button>
              ))}
            </div>
          )}

          {this.loading ? (
            <GwSkeleton />
          ) : filtered.length === 0 ? (
            <div className="GiveawaysPage-empty">
              {app.translator.trans('ernestdefoe-giveaways.forum.empty')}
            </div>
          ) : (
            [
              active.length > 0 && (
                <section
                  /* 🚨 Measured on the element's own lifecycle, not after the
                     fetch: a requestAnimationFrame there races Mithril's
                     redraw and can run before anything has been drawn. */
                  oncreate={() => rememberSections([active.length, past.length])}
                  onupdate={() => rememberSections([active.length, past.length])}
                >
                  <h2 className="GiveawaysPage-sectionTitle">
                    {app.translator.trans('ernestdefoe-giveaways.forum.active_heading')}
                  </h2>
                  <div className="GiveawaysPage-grid">
                    {active.map((g) => (
                      <GiveawayCard key={g.id} giveaway={g} />
                    ))}
                  </div>
                </section>
              ),
              past.length > 0 && (
                <section
                  /* Also here: a forum with only finished giveaways renders no
                     active section, and would otherwise remember nothing. */
                  oncreate={() => rememberSections([active.length, past.length])}
                  onupdate={() => rememberSections([active.length, past.length])}
                >
                  <h2 className="GiveawaysPage-sectionTitle">
                    {app.translator.trans('ernestdefoe-giveaways.forum.past_heading')}
                  </h2>
                  <div className="GiveawaysPage-grid">
                    {past.map((g) => (
                      <GiveawayCard key={g.id} giveaway={g} />
                    ))}
                  </div>
                </section>
              ),
            ]
          )}
        </div>
      </div>
    );
  }
}
