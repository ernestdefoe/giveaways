import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import GwSkeleton, { rememberSections } from '../components/GwSkeleton';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';

import { listGiveaways, listCategories } from '../../common/api';
import type { Giveaway, GiveawayCategory } from '../../common/api';
import GiveawayCard from '../components/GiveawayCard';

type Tab = 'all' | 'open' | 'ended' | 'drafts';

export default class GiveawaysPage extends Page {
  loading = true;
  giveaways: Giveaway[] = [];
  categories: GiveawayCategory[] = [];
  canCreate = false;
  canManage = false;
  filter: number | null = null;
  tab: Tab = 'all';

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
    app.modal.show(() => import('../components/GiveawayFormModal'), { categories: this.categories, onsave: () => this.load() });
  }

  manageCategories() {
    app.modal.show(() => import('../components/CategoryManagerModal'), { onchange: () => this.loadCategories() });
  }

  matchesFilter(g: Giveaway): boolean {
    return this.filter === null || (g.category && g.category.id === this.filter) || false;
  }

  view(): Mithril.Children {
    const filtered = this.giveaways.filter((g) => this.matchesFilter(g));
    // Only the viewer's own drafts ever come back, so a Drafts tab only shows
    // for someone who has some.
    const drafts = this.giveaways.filter((g) => g.status === 'draft');
    if (this.tab === 'drafts' && !drafts.length) this.tab = 'all';

    const active = this.tab === 'all' || this.tab === 'open' ? filtered.filter((g) => g.status === 'active') : [];
    const past = this.tab === 'all' || this.tab === 'ended' ? filtered.filter((g) => g.status === 'drawn' || g.status === 'cancelled') : [];
    const draftsShown = this.tab === 'drafts' ? filtered.filter((g) => g.status === 'draft') : [];
    const shown = active.length + past.length + draftsShown.length;

    return (
      <div className="GiveawaysPage">
        <div className="GiveawaysPage-hero">
          <div className="container">
            <h1 className="GiveawaysPage-title">{app.translator.trans('ernestdefoe-giveaways.forum.heading')}</h1>
            <p className="GiveawaysPage-subtitle">{app.translator.trans('ernestdefoe-giveaways.forum.subheading')}</p>
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
          <nav className="GiveawaysPage-tabs" role="tablist">
            {(['all', 'open', 'ended', 'drafts'] as Tab[])
              .filter((t) => t !== 'drafts' || drafts.length > 0)
              .map((t) => (
                <button
                  type="button"
                  role="tab"
                  aria-selected={this.tab === t}
                  className={'GiveawaysPage-tab' + (this.tab === t ? ' is-active' : '')}
                  onclick={() => (this.tab = t)}
                >
                  {app.translator.trans(`ernestdefoe-giveaways.forum.tabs.${t}`)}
                  {t === 'drafts' && <span className="GiveawaysPage-tabCount">{drafts.length}</span>}
                </button>
              ))}
          </nav>

          {this.categories.length > 0 && (
            <div className="GiveawaysPage-filters">
              <button
                className={'GiveawayFilter' + (this.filter === null ? ' is-active' : '')}
                onclick={() => {
                  this.filter = null;
                }}
              >
                {app.translator.trans('ernestdefoe-giveaways.forum.categories.all')}
              </button>
              {this.categories.map((c) => (
                <button
                  key={c.id}
                  className={'GiveawayFilter' + (this.filter === c.id ? ' is-active' : '')}
                  style={
                    this.filter === c.id
                      ? { backgroundColor: c.color, borderColor: c.color, color: '#fff' }
                      : { color: c.color, borderColor: c.color }
                  }
                  onclick={() => {
                    this.filter = c.id;
                  }}
                >
                  {c.icon && <Icon name={c.icon} />} {c.name}
                </button>
              ))}
            </div>
          )}

          {this.loading ? (
            <GwSkeleton />
          ) : shown === 0 ? (
            <div className="GiveawaysPage-empty">{app.translator.trans('ernestdefoe-giveaways.forum.empty')}</div>
          ) : (
            [
              active.length > 0 && (
                <section
                  /* 🚨 Measured on the element's own lifecycle, not after the
                     fetch: a requestAnimationFrame there races Mithril's
                     redraw and can run before anything has been drawn. */
                  oncreate={() => this.tab === 'all' && rememberSections([active.length, past.length])}
                  onupdate={() => this.tab === 'all' && rememberSections([active.length, past.length])}
                >
                  <h2 className="GiveawaysPage-sectionTitle">{app.translator.trans('ernestdefoe-giveaways.forum.active_heading')}</h2>
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
                  oncreate={() => this.tab === 'all' && rememberSections([active.length, past.length])}
                  onupdate={() => this.tab === 'all' && rememberSections([active.length, past.length])}
                >
                  <h2 className="GiveawaysPage-sectionTitle">{app.translator.trans('ernestdefoe-giveaways.forum.past_heading')}</h2>
                  <div className="GiveawaysPage-grid">
                    {past.map((g) => (
                      <GiveawayCard key={g.id} giveaway={g} />
                    ))}
                  </div>
                </section>
              ),
              draftsShown.length > 0 && (
                <section>
                  <h2 className="GiveawaysPage-sectionTitle">{app.translator.trans('ernestdefoe-giveaways.forum.drafts_heading')}</h2>
                  <p className="helpText">{app.translator.trans('ernestdefoe-giveaways.forum.drafts_help')}</p>
                  <div className="GiveawaysPage-grid">
                    {draftsShown.map((g) => (
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
