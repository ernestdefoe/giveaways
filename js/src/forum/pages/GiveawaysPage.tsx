import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';

import { listGiveaways } from '../../common/api';
import type { Giveaway } from '../../common/api';
import GiveawayCard from '../components/GiveawayCard';
import GiveawayFormModal from '../components/GiveawayFormModal';

export default class GiveawaysPage extends Page {
  loading = true;
  giveaways: Giveaway[] = [];
  canCreate = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(app.translator.trans('ernestdefoe-giveaways.forum.page_title') as string);
    this.load();
  }

  load() {
    this.loading = true;
    listGiveaways()
      .then((res) => {
        this.giveaways = res.data || [];
        this.canCreate = !!(res.meta && res.meta.canCreate);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  create() {
    app.modal.show(GiveawayFormModal, { onsave: () => this.load() });
  }

  view(): Mithril.Children {
    const active = this.giveaways.filter((g) => g.status === 'active');
    const past = this.giveaways.filter((g) => g.status !== 'active');

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
            {this.canCreate && (
              <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.create()}>
                {app.translator.trans('ernestdefoe-giveaways.forum.create')}
              </Button>
            )}
          </div>
        </div>

        <div className="container GiveawaysPage-content">
          {this.loading ? (
            <LoadingIndicator />
          ) : this.giveaways.length === 0 ? (
            <div className="GiveawaysPage-empty">
              {app.translator.trans('ernestdefoe-giveaways.forum.empty')}
            </div>
          ) : (
            [
              active.length > 0 && (
                <section>
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
                <section>
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
