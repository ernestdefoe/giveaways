import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import LogInModal from 'flarum/forum/components/LogInModal';

import { showGiveaway, enterGiveaway, drawGiveaway, deleteGiveaway } from '../../common/api';
import type { Giveaway } from '../../common/api';
import { countdown } from '../../common/format';
import GiveawayFormModal from '../components/GiveawayFormModal';

export default class GiveawayPage extends Page {
  loading = true;
  entering = false;
  giveaway: Giveaway | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    const slug = m.route.param('slug');
    this.loading = true;
    showGiveaway(slug)
      .then((res) => {
        this.giveaway = res.data;
        app.setTitle(this.giveaway.title);
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  enter() {
    const g = this.giveaway!;
    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }
    this.entering = true;
    enterGiveaway(g.id)
      .then((res) => {
        this.giveaway = res.data;
        this.entering = false;
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.enter_success'));
        m.redraw();
      })
      .catch(() => {
        this.entering = false;
        m.redraw();
      });
  }

  draw() {
    const g = this.giveaway!;
    if (!confirm(app.translator.trans('ernestdefoe-giveaways.forum.confirm_draw') as string)) return;
    drawGiveaway(g.id).then((res) => {
      this.giveaway = res.data;
      m.redraw();
    });
  }

  edit() {
    app.modal.show(GiveawayFormModal, { giveaway: this.giveaway, onsave: () => this.load() });
  }

  remove() {
    const g = this.giveaway!;
    if (!confirm(app.translator.trans('ernestdefoe-giveaways.forum.confirm_delete') as string)) return;
    deleteGiveaway(g.id).then(() => m.route.set(app.route('giveaways.index')));
  }

  view(): Mithril.Children {
    if (this.loading) return <div className="GiveawayPage"><LoadingIndicator /></div>;
    const g = this.giveaway;
    if (!g) {
      return (
        <div className="GiveawayPage container">
          <p>{app.translator.trans('ernestdefoe-giveaways.forum.empty')}</p>
        </div>
      );
    }

    return (
      <div className="GiveawayPage">
        <div
          className={'GiveawayPage-hero' + (g.coverUrl ? '' : ' GiveawayPage-hero--plain')}
          style={g.coverUrl ? { backgroundImage: `url("${g.coverUrl}")` } : undefined}
        >
          <div className="GiveawayPage-hero-overlay">
            <div className="container">
              <Link className="GiveawayPage-back" href={app.route('giveaways.index')}>
                <Icon name="fas fa-chevron-left" />{' '}
                {app.translator.trans('ernestdefoe-giveaways.forum.nav')}
              </Link>
              <span className={`GiveawayCard-status GiveawayCard-status--${g.status}`}>
                {app.translator.trans(`ernestdefoe-giveaways.forum.status_${g.status}`)}
              </span>
              {g.category && (
                <span className="GiveawayPage-category" style={{ backgroundColor: g.category.color }}>
                  {g.category.icon && <Icon name={g.category.icon} />} {g.category.name}
                </span>
              )}
              <h1 className="GiveawayPage-title">{g.title}</h1>
              <div className="GiveawayPage-prize">
                <Icon name="fas fa-trophy" /> {g.prize}
              </div>
            </div>
          </div>
        </div>

        <div className="container GiveawayPage-content">
          <div className="GiveawayPage-main">
            {this.descriptionBlock(g)}
            {this.requirementsBlock(g)}
            {this.winnersBlock(g)}
            {this.fairnessBlock(g)}
          </div>
          <aside className="GiveawayPage-side">
            {this.actionBox(g)}
            {g.canManage && this.manageBox(g)}
          </aside>
        </div>
      </div>
    );
  }

  descriptionBlock(g: Giveaway): Mithril.Children {
    if (!g.description) return null;
    return (
      <section className="GiveawayPage-section">
        <h2>{app.translator.trans('ernestdefoe-giveaways.forum.description_label')}</h2>
        <div className="GiveawayPage-description">
          {g.description.split('\n').map((line) => (line.trim() ? <p>{line}</p> : null))}
        </div>
      </section>
    );
  }

  requirementsBlock(g: Giveaway): Mithril.Children {
    const reqs: Mithril.Children[] = [];
    if (g.minPosts > 0)
      reqs.push(<li><Icon name="fas fa-comment" /> {app.translator.trans('ernestdefoe-giveaways.forum.req_min_posts', { count: g.minPosts })}</li>);
    if (g.minAgeDays > 0)
      reqs.push(<li><Icon name="fas fa-hourglass-half" /> {app.translator.trans('ernestdefoe-giveaways.forum.req_min_age', { count: g.minAgeDays })}</li>);

    return (
      <section className="GiveawayPage-section">
        <h2>{app.translator.trans('ernestdefoe-giveaways.forum.requirements_label')}</h2>
        <ul className="GiveawayPage-reqs">
          {reqs.length ? reqs : <li><Icon name="fas fa-check" /> {app.translator.trans('ernestdefoe-giveaways.forum.no_requirements')}</li>}
        </ul>
      </section>
    );
  }

  winnersBlock(g: Giveaway): Mithril.Children {
    if (g.status !== 'drawn') return null;
    const winners = g.winners || [];
    return (
      <section className="GiveawayPage-section">
        <h2>{app.translator.trans('ernestdefoe-giveaways.forum.winners_label')}</h2>
        {winners.length === 0 ? (
          <p>{app.translator.trans('ernestdefoe-giveaways.forum.no_winners')}</p>
        ) : (
          <ul className="GiveawayPage-winners">
            {winners.map((w) => (
              <li className="GiveawayPage-winner">
                <span className="GiveawayPage-winner-pos">#{w.position}</span>
                {w.user ? (
                  <Link href={app.route('user', { username: w.user.username })} className="GiveawayPage-winner-user">
                    <img className="Avatar" src={w.user.avatarUrl || ''} alt="" />
                    <span>{w.user.displayName}</span>
                  </Link>
                ) : (
                  <span className="GiveawayPage-winner-user">—</span>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    );
  }

  fairnessBlock(g: Giveaway): Mithril.Children {
    if (g.status !== 'drawn' || !g.drawSeed) return null;
    return (
      <section className="GiveawayPage-section GiveawayPage-fairness">
        <h2><Icon name="fas fa-shield-alt" /> {app.translator.trans('ernestdefoe-giveaways.forum.fairness_label')}</h2>
        <p>{app.translator.trans('ernestdefoe-giveaways.forum.fairness_intro')}</p>
        <div className="GiveawayPage-fairnessField">
          <label>{app.translator.trans('ernestdefoe-giveaways.forum.seed_label')}</label>
          <code>{g.drawSeed}</code>
        </div>
        <div className="GiveawayPage-fairnessField">
          <label>{app.translator.trans('ernestdefoe-giveaways.forum.hash_label')}</label>
          <code>{g.entrantHash}</code>
        </div>
        <p className="GiveawayPage-fairnessHelp helpText">
          {app.translator.trans('ernestdefoe-giveaways.forum.fairness_help')}
        </p>
      </section>
    );
  }

  actionBox(g: Giveaway): Mithril.Children {
    const active = g.status === 'active';
    const entered = g.myEntries > 0;

    return (
      <div className="GiveawayPage-actionBox">
        <div className="GiveawayPage-stat">
          <strong>{g.entrantCount}</strong>
          <span>{app.translator.trans('ernestdefoe-giveaways.forum.entrants', { count: g.entrantCount })}</span>
        </div>

        {active ? (
          <div className="GiveawayPage-countdown">
            <Icon name="fas fa-clock" />{' '}
            {app.translator.trans('ernestdefoe-giveaways.forum.ends_in', { time: countdown(g.endsAt) })}
          </div>
        ) : (
          <div className="GiveawayPage-countdown">
            {app.translator.trans('ernestdefoe-giveaways.forum.ended')}
            {g.drawnAt ? [' · ', humanTime(g.drawnAt)] : null}
          </div>
        )}

        {active &&
          (entered ? (
            <div className="GiveawayPage-entered">
              <Icon name="fas fa-check-circle" />{' '}
              {app.translator.trans('ernestdefoe-giveaways.forum.your_entries', { count: g.myEntries })}
            </div>
          ) : !app.session.user ? (
            <Button className="Button Button--primary Button--block" onclick={() => this.enter()}>
              {app.translator.trans('ernestdefoe-giveaways.forum.login_to_enter')}
            </Button>
          ) : (
            <Button
              className="Button Button--primary Button--block"
              icon="fas fa-ticket-alt"
              loading={this.entering}
              onclick={() => this.enter()}
            >
              {app.translator.trans('ernestdefoe-giveaways.forum.enter')}
            </Button>
          ))}

        {active && entered && g.postBonus > 0 && this.earnMore(g)}
      </div>
    );
  }

  earnMore(g: Giveaway): Mithril.Children {
    const sources = g.mySources || {};
    const got = !!sources['post'];
    return (
      <div className="GiveawayPage-earn">
        <h4>{app.translator.trans('ernestdefoe-giveaways.forum.earn_more')}</h4>
        <div className={'GiveawayPage-earnItem' + (got ? ' is-done' : '')}>
          <Icon name={got ? 'fas fa-check-circle' : 'far fa-circle'} />
          {got
            ? app.translator.trans('ernestdefoe-giveaways.forum.earned_post', { count: g.postBonus })
            : app.translator.trans('ernestdefoe-giveaways.forum.earn_post', { count: g.postBonus })}
        </div>
      </div>
    );
  }

  manageBox(g: Giveaway): Mithril.Children {
    return (
      <div className="GiveawayPage-manage">
        <Button className="Button Button--block" icon="fas fa-pencil-alt" onclick={() => this.edit()}>
          {app.translator.trans('ernestdefoe-giveaways.forum.edit')}
        </Button>
        {g.status === 'active' && (
          <Button className="Button Button--block" icon="fas fa-dice" onclick={() => this.draw()}>
            {app.translator.trans('ernestdefoe-giveaways.forum.draw_now')}
          </Button>
        )}
        <Button className="Button Button--block GiveawayPage-delete" icon="fas fa-trash" onclick={() => this.remove()}>
          {app.translator.trans('ernestdefoe-giveaways.forum.delete')}
        </Button>
      </div>
    );
  }
}
