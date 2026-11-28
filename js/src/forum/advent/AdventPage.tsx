import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import AdventDoor from './AdventDoor';
import SponsorBanner from '../sponsors/SponsorBanner';
import ConsentBox from '../sponsors/ConsentBox';
import { forgetToday } from './todayWidget';
import { countdown } from '../../common/format';
import { openDoor, setReminder, showCalendar, shuffled } from '../../common/advent';
import type { AdventCalendar, AdventDoor as Door } from '../../common/advent';

/**
 * /advent/:slug — the calendar. A grid of doors in a fixed shuffled order;
 * today's can be opened, a missed one with a joker; the door picked shows
 * what was behind it (or when it opens) underneath.
 */
export default class AdventPage extends Page {
  calendar: AdventCalendar | null = null;
  missing = false;
  selected: number | null = null;
  busy = false;
  /** Redraws the countdown once a minute; cleared on remove. */
  timer = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
    this.timer = window.setInterval(() => m.redraw(), 60000);
  }

  onremove(vnode: Mithril.VnodeDOM) {
    super.onremove(vnode);
    window.clearInterval(this.timer);
  }

  load() {
    showCalendar(m.route.param('slug'))
      .then((calendar) => {
        this.calendar = calendar;
        app.setTitle(calendar.title);
        // Start on today's door.
        this.selected ??= calendar.today >= 1 && calendar.today <= calendar.days ? calendar.today : null;
        m.redraw();
      })
      .catch(() => {
        this.missing = true;
        m.redraw();
      });
  }

  view(): Mithril.Children {
    const c = this.calendar;
    if (this.missing) {
      return <div className="AdventPage container">{app.translator.trans('ernestdefoe-giveaways.forum.advent.not_found')}</div>;
    }
    if (!c) {
      return <LoadingIndicator display="block" />;
    }

    const order = shuffled(c.doors, Number(c.id));
    const door = this.selected ? c.doors[this.selected - 1] : null;

    return (
      <div className="AdventPage">
        <div className="container">
          <header className="AdventPage-header">
            <h2 className="AdventPage-title">{c.title}</h2>
            {c.sponsor && <SponsorBanner sponsor={c.sponsor} />}
            {c.status === 'draft' && <span className="AdventPage-draft">{app.translator.trans('ernestdefoe-giveaways.forum.advent.draft')}</span>}
            {c.descriptionHtml && <div className="AdventPage-description Post-body">{m.trust(c.descriptionHtml)}</div>}
            <p className="AdventPage-zone">
              <Icon name="far fa-clock" /> {app.translator.trans('ernestdefoe-giveaways.forum.advent.zone', { zone: c.zone })}
            </p>
            {this.status(c)}
            {this.chips(c)}
          </header>

          {c.ineligible && <p className="AdventPage-notice">{c.ineligible}</p>}

          {this.consent(c)}

          <div className="AdventGrid" role="group" aria-label={extractText(app.translator.trans('ernestdefoe-giveaways.forum.advent.grid_label'))}>
            {order.map((d, i) => (
              <AdventDoor door={d} tint={i % 4} selected={this.selected === d.day} onclick={() => this.pick(d)} />
            ))}
          </div>

          {door && this.detail(c, door)}
          {this.grand(c)}
        </div>
      </div>
    );
  }

  /** The sponsor's opt-in box, while the calendar runs, for signed-in members. */
  consent(c: AdventCalendar): Mithril.Children {
    if (!c.sponsor || !c.sponsorConsentText || !app.session.user || c.status !== 'published' || c.today > c.days) return null;

    return (
      <ConsentBox
        sponsor={c.sponsor}
        campaign="calendar"
        campaignId={c.id}
        checked={!!c.myConsent}
        text={c.sponsorConsentText}
        onchange={(on: boolean) => (c.myConsent = on)}
      />
    );
  }

  status(c: AdventCalendar): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.${key}`, params);

    if (c.today < 1) return <p className="AdventPage-status">{t('starts_in', { time: countdown(c.doors[0].opensAt) })}</p>;
    if (c.today > c.days) return <p className="AdventPage-status">{t('over')}</p>;

    const next = c.today < c.days ? c.doors[c.today].opensAt : c.endsAt;
    return (
      <p className="AdventPage-status">
        {t('today', { day: c.today })} · {t(c.today < c.days ? 'next_in' : 'ends_in', { time: countdown(next) })}
      </p>
    );
  }

  chips(c: AdventCalendar): Mithril.Children {
    const signedIn = !!app.session.user;
    const left = c.jokers - (c.jokersUsed || 0);

    return (
      <div className="AdventPage-chips">
        {signedIn && (
          <span className="AdventChip">
            <Icon name="fas fa-ticket-alt" /> {app.translator.trans('ernestdefoe-giveaways.forum.advent.tickets', { count: c.tickets })}
          </span>
        )}
        {signedIn && c.jokers > 0 && (
          <span className="AdventChip">
            <Icon name="fas fa-hat-wizard" />{' '}
            {app.translator.trans('ernestdefoe-giveaways.forum.advent.jokers_left', { count: left, total: c.jokers })}
          </span>
        )}
        {signedIn && c.status === 'published' && c.today <= c.days && (
          <Button className={'Button Button--sm' + (c.remind ? ' Button--primary' : '')} icon="fas fa-bell" onclick={() => this.toggleReminder()}>
            {app.translator.trans(`ernestdefoe-giveaways.forum.advent.${c.remind ? 'remind_on' : 'remind_off'}`)}
          </Button>
        )}
        {c.canManage && (
          <Button className="Button Button--sm" icon="fas fa-pencil-alt" onclick={() => this.edit()}>
            {app.translator.trans('ernestdefoe-giveaways.forum.advent.edit')}
          </Button>
        )}
        {!signedIn && (
          <Button className="Button Button--sm Button--primary" onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}>
            {app.translator.trans('ernestdefoe-giveaways.forum.advent.log_in')}
          </Button>
        )}
      </div>
    );
  }

  detail(c: AdventCalendar, door: Door): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.${key}`, params);
    const date = dayjs(door.opensAt).format('LL');
    const canAct = !!app.session.user && !c.ineligible && c.status === 'published';
    const jokersLeft = c.jokers - (c.jokersUsed || 0);

    let body: Mithril.Children;
    if (door.state === 'locked') {
      body = <p>{t('opens_on', { date, time: countdown(door.opensAt) })}</p>;
    } else if (door.state === 'ready') {
      body = canAct ? (
        <Button className="Button Button--primary" icon="fas fa-door-open" loading={this.busy} onclick={() => this.open(door.day, false)}>
          {t('open_today', { day: door.day })}
        </Button>
      ) : (
        <p>{t('ready', { day: door.day })}</p>
      );
    } else if (door.state === 'missed') {
      body = [
        <p>{t('missed', { day: door.day })}</p>,
        canAct && c.today <= c.days && jokersLeft > 0 ? (
          <Button className="Button" icon="fas fa-hat-wizard" loading={this.busy} onclick={() => this.open(door.day, true)}>
            {t('use_joker')}
          </Button>
        ) : (
          canAct && c.today <= c.days && <p className="helpText">{t('no_jokers')}</p>
        ),
      ];
    } else {
      body = this.content(door);
    }

    return (
      <section className={`AdventDetail AdventDetail--${door.state}`} aria-live="polite">
        <h3 className="AdventDetail-title">
          {t('door', { day: door.day })}
          {door.state === 'opened' && door.title && <span> · {door.title}</span>}
          {door.joker && <span className="AdventDetail-joker">{t('opened_with_joker')}</span>}
        </h3>
        {body}
      </section>
    );
  }

  content(door: Door): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.${key}`, params);

    return [
      door.html && <div className="AdventDetail-body Post-body">{m.trust(door.html)}</div>,
      door.draw && (
        <p className="AdventDetail-draw">
          <Icon name="fas fa-trophy" /> {door.draw.prize}
          {door.draw.winnerCount > 1 && ` × ${door.draw.winnerCount}`} · {door.draw.entered ? t('draw_entered') : t('draw_not_entered')} ·{' '}
          <Link href={app.route('giveaways.show', { slug: door.draw.slug })}>{t(door.draw.status === 'drawn' ? 'see_winners' : 'see_draw')}</Link>
        </p>
      ),
      door.exchange && (
        <p className="AdventDetail-exchange">
          <Icon name="fas fa-mitten" /> {door.exchange.title} ·{' '}
          <Link href={app.route('secret-santa.exchange', { id: door.exchange.id })}>
            {t(door.exchange.signupsOpen ? 'exchange_join' : 'exchange_view')}
          </Link>
        </p>
      ),
      !door.html && !door.draw && !door.exchange && <p>{t('ticket_only')}</p>,
    ];
  }

  grand(c: AdventCalendar): Mithril.Children {
    if (!c.grand) return null;
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.${key}`, params);
    // The grand prize's page exists once the first door has opened.
    const prize = (
      <span>
        {c.grand.prize}
        {c.grand.winnerCount > 1 && ` × ${c.grand.winnerCount}`}
      </span>
    );

    return (
      <section className="AdventGrand">
        <Icon name="fas fa-trophy" className="AdventGrand-icon" />
        <div>
          <h3 className="AdventGrand-title">{t('grand_title')}</h3>
          <p className="AdventGrand-prize">
            {c.today >= 1 ? <Link href={app.route('giveaways.show', { slug: c.grand.slug })}>{prize}</Link> : prize}
          </p>
          <p className="helpText">{t('grand_help', { date: dayjs(c.endsAt).format('LLL') })}</p>
        </div>
      </section>
    );
  }

  pick(door: Door) {
    this.selected = door.day;
    this.reveal();
    if (door.state === 'ready' && this.calendar && app.session.user && !this.calendar.ineligible && this.calendar.status === 'published') {
      this.open(door.day, false);
    }
  }

  open(day: number, joker: boolean) {
    if (!this.calendar || this.busy) return;
    this.busy = true;
    openDoor(this.calendar.id, day, joker)
      .then((calendar) => {
        this.calendar = calendar;
        this.selected = day;
        this.reveal();
        forgetToday();
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  /** On a phone the door's details sit below all the doors: bring them into view. */
  reveal() {
    requestAnimationFrame(() => {
      const detail = document.querySelector('.AdventDetail');
      const box = detail?.getBoundingClientRect();
      if (detail && box && (box.top < 0 || box.bottom > window.innerHeight)) {
        detail.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      }
    });
  }

  toggleReminder() {
    if (!this.calendar) return;
    setReminder(this.calendar.id, !this.calendar.remind)
      .then((calendar) => {
        this.calendar = calendar;
        m.redraw();
      })
      .catch(() => {
        /* app.request already showed the alert */
      });
  }

  edit() {
    app.modal.show(() => import('./CalendarBuilderModal'), {
      calendar: this.calendar,
      onsave: (calendar: AdventCalendar | null) => {
        if (!calendar) {
          m.route.set(app.route('giveaways.index'));
          return;
        }
        if (calendar.slug !== m.route.param('slug')) {
          m.route.set(app.route('giveaways.advent', { slug: calendar.slug }));
          return;
        }
        this.load();
      },
    });
  }
}
