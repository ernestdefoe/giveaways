import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import TextEditor from 'flarum/common/components/TextEditor';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { deleteCalendar, saveCalendar } from '../../common/advent';
import type { AdventCalendar, EditorDoor } from '../../common/advent';

export interface CalendarBuilderAttrs extends IInternalModalAttrs {
  calendar?: AdventCalendar | null;
  /** The saved calendar, or null once it has been deleted. */
  onsave?: (calendar: AdventCalendar | null) => void;
}

type Form = {
  title: string;
  description: string;
  startsOn: string;
  days: number;
  timezone: string;
  minPosts: number;
  minAgeDays: number;
  grandPrize: string;
  grandWinners: number;
  grandClaimInstructions: string;
  grandClaimLines: string;
};

const KINDS: EditorDoor['kind'][] = ['reveal', 'prize', 'exchange'];

function blankDoor(day: number): EditorDoor {
  return { day, kind: 'reveal', title: '', body: '', prize: '', winners: 1, claimInstructions: '', claimLines: '', exchangeId: null };
}

/**
 * Builds or edits an advent calendar: its dates, the grand prize, and what is
 * behind each door. Once the first door has opened the dates are fixed, and
 * so is what each opened door hands out (the server enforces both; the form
 * just greys them out).
 */
export default class CalendarBuilderModal extends FormModal<CalendarBuilderAttrs> {
  form!: Form;
  doors: EditorDoor[] = [];
  open: number | null = null;

  oninit(vnode: Mithril.Vnode<CalendarBuilderAttrs, this>) {
    super.oninit(vnode);
    const c = this.attrs.calendar;
    const december = `${new Date().getFullYear()}-12-01`;

    this.form = {
      title: c?.title ?? '',
      description: c?.description ?? '',
      startsOn: c?.startsOn ?? december,
      days: c?.days ?? 24,
      timezone: c?.timezone ?? '',
      minPosts: c?.minPosts ?? 0,
      minAgeDays: c?.minAgeDays ?? 0,
      grandPrize: c?.grandPrize ?? '',
      grandWinners: c?.grandWinners ?? 1,
      grandClaimInstructions: c?.grandClaimInstructions ?? '',
      grandClaimLines: c?.grandClaimLines ?? '',
    };
    const saved = new Map((c?.editor ?? []).map((d) => [d.day, { ...d, title: d.title ?? '', prize: d.prize ?? '' }]));
    this.doors = Array.from({ length: 31 }, (_, i) => saved.get(i + 1) ?? blankDoor(i + 1));
  }

  className() {
    return 'CalendarBuilderModal Modal--large';
  }

  title() {
    return app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${this.attrs.calendar ? 'edit_title' : 'new_title'}`);
  }

  /** The first door has opened: dates fixed. */
  started(): boolean {
    const c = this.attrs.calendar;
    return !!c && c.status === 'published' && c.today >= 1;
  }

  content(): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const f = this.form;

    return (
      <div className="Modal-body">
        <div className="Form">
          {this.text(t('title'), 'title')}
          <div className="Form-group">
            <label>{t('description')}</label>
            {this.editor(
              'description',
              () => f.description,
              (v) => (f.description = v)
            )}
          </div>

          {this.dates()}

          <fieldset className="CalendarBuilder-row">
            {this.number(t('min_posts'), 'minPosts', 0, 100000)}
            {this.number(t('min_age_days'), 'minAgeDays', 0, 100000)}
          </fieldset>

          {this.grandSection()}

          <h4 className="CalendarBuilder-heading">{t('doors_heading')}</h4>
          <p className="helpText">{t('doors_help')}</p>
          <ol className="CalendarBuilder-doors">{this.doors.slice(0, Math.max(1, Math.min(31, f.days))).map((d) => this.door(d))}</ol>

          {this.actions()}
        </div>
      </div>
    );
  }

  /** The first door's date, how many doors, the timezone: fixed once the first door opens. */
  dates(): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const f = this.form;
    const started = this.started();
    const zones: string[] = (Intl as unknown as { supportedValuesOf?: (key: string) => string[] }).supportedValuesOf?.('timeZone') ?? [];

    return (
      <>
        <fieldset className="CalendarBuilder-row">
          <div className="Form-group">
            <label>{t('starts_on')}</label>
            <input
              type="date"
              className="FormControl"
              value={f.startsOn}
              disabled={started}
              oninput={(e: Event) => (f.startsOn = (e.target as HTMLInputElement).value)}
            />
          </div>
          {this.number(t('days'), 'days', 1, 31, started)}
          <div className="Form-group">
            <label>{t('timezone')}</label>
            <input
              className="FormControl"
              list="CalendarBuilder-zones"
              value={f.timezone}
              disabled={started}
              placeholder={extractText(t('timezone_placeholder'))}
              oninput={(e: Event) => (f.timezone = (e.target as HTMLInputElement).value.trim())}
            />
            <datalist id="CalendarBuilder-zones">
              {zones.map((z) => (
                <option value={z} />
              ))}
            </datalist>
          </div>
        </fieldset>
        {started && <p className="helpText">{t('dates_locked')}</p>}
      </>
    );
  }

  grandSection(): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const f = this.form;

    return (
      <>
        <h4 className="CalendarBuilder-heading">{t('grand_heading')}</h4>
        <p className="helpText">{t('grand_help')}</p>
        <fieldset className="CalendarBuilder-row">
          {this.text(t('grand_prize'), 'grandPrize')}
          {this.number(t('winners'), 'grandWinners', 1, 100)}
        </fieldset>
        {this.area(
          t('claim_instructions'),
          () => f.grandClaimInstructions,
          (v) => (f.grandClaimInstructions = v),
          t('claim_instructions_help')
        )}
        {this.area(
          t('claim_lines'),
          () => f.grandClaimLines,
          (v) => (f.grandClaimLines = v),
          t('claim_lines_help')
        )}
      </>
    );
  }

  actions(): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const started = this.started();

    return (
      <div className="Form-group CalendarBuilder-actions">
        <Button type="submit" className="Button Button--primary" loading={this.loading} onclick={() => this.submitAs(true)}>
          {t(this.attrs.calendar?.status === 'published' ? 'save' : 'publish')}
        </Button>
        {!started && (
          <Button type="submit" className="Button" loading={this.loading} onclick={() => this.submitAs(false)}>
            {t('save_draft')}
          </Button>
        )}
        {this.attrs.calendar && (
          <Button className="Button Button--danger" onclick={() => this.remove()}>
            {t('delete')}
          </Button>
        )}
      </div>
    );
  }

  door(d: EditorDoor): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const c = this.attrs.calendar;
    // An opened door keeps what it hands out.
    const locked = this.started() && !!c && d.day <= c.today;
    const expanded = this.open === d.day;

    return (
      <li className={'CalendarBuilder-door' + (expanded ? ' is-open' : '')}>
        <button type="button" className="CalendarBuilder-doorHead" aria-expanded={expanded} onclick={() => (this.open = expanded ? null : d.day)}>
          <span className="CalendarBuilder-day">{d.day}</span>
          <span className="CalendarBuilder-summary">
            {t(`kind_${d.kind}`)}
            {d.title ? ` · ${d.title}` : ''}
            {d.kind === 'prize' && d.prize ? ` · ${d.prize}` : ''}
          </span>
          {locked && <span className="CalendarBuilder-locked">{t('door_opened')}</span>}
        </button>
        {expanded && <div className="CalendarBuilder-doorBody">{this.doorFields(d, locked)}</div>}
      </li>
    );
  }

  doorFields(d: EditorDoor, locked: boolean): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);
    const choices = this.attrs.calendar?.exchangeChoices ?? [];

    return (
      <>
        <div className="Form-group">
          <label>{t('kind')}</label>
          <select
            className="FormControl"
            value={d.kind}
            disabled={locked}
            onchange={(e: Event) => (d.kind = (e.target as HTMLSelectElement).value as EditorDoor['kind'])}
          >
            {KINDS.filter((k) => k !== 'exchange' || choices.length || d.kind === 'exchange').map((k) => (
              <option value={k}>{t(`kind_${k}`)}</option>
            ))}
          </select>
          <p className="helpText">{t(`kind_${d.kind}_help`)}</p>
        </div>
        <div className="Form-group">
          <label>{t('door_title')}</label>
          <input className="FormControl" value={d.title ?? ''} oninput={(e: Event) => (d.title = (e.target as HTMLInputElement).value)} />
        </div>
        <div className="Form-group">
          <label>{t('door_body')}</label>
          {this.editor(
            `door-${d.day}`,
            () => d.body,
            (v) => (d.body = v)
          )}
        </div>
        {d.kind === 'prize' && this.prizeFields(d, locked)}
        {d.kind === 'exchange' && (
          <div className="Form-group">
            <label>{t('exchange')}</label>
            <select
              className="FormControl"
              value={String(d.exchangeId ?? '')}
              onchange={(e: Event) => (d.exchangeId = parseInt((e.target as HTMLSelectElement).value, 10) || null)}
            >
              <option value="">{t('exchange_pick')}</option>
              {choices.map((x) => (
                <option value={String(x.id)}>{x.title}</option>
              ))}
            </select>
          </div>
        )}
      </>
    );
  }

  prizeFields(d: EditorDoor, locked: boolean): Mithril.Children {
    const t = (key: string, params = {}) => app.translator.trans(`ernestdefoe-giveaways.forum.advent.builder.${key}`, params);

    return [
      <fieldset className="CalendarBuilder-row">
        <div className="Form-group">
          <label>{t('door_prize')}</label>
          <input
            className="FormControl"
            value={d.prize ?? ''}
            disabled={locked}
            oninput={(e: Event) => (d.prize = (e.target as HTMLInputElement).value)}
          />
        </div>
        <div className="Form-group">
          <label>{t('winners')}</label>
          <input
            type="number"
            className="FormControl"
            min={1}
            max={100}
            value={d.winners}
            disabled={locked}
            oninput={(e: Event) => (d.winners = parseInt((e.target as HTMLInputElement).value, 10) || 1)}
          />
        </div>
      </fieldset>,
      this.area(
        t('claim_instructions'),
        () => d.claimInstructions,
        (v) => (d.claimInstructions = v),
        t('claim_instructions_help')
      ),
      this.area(
        t('claim_lines'),
        () => d.claimLines,
        (v) => (d.claimLines = v),
        t('claim_lines_help')
      ),
    ];
  }

  text(label: Mithril.Children, key: 'title' | 'grandPrize'): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <input className="FormControl" value={this.form[key]} oninput={(e: Event) => (this.form[key] = (e.target as HTMLInputElement).value)} />
      </div>
    );
  }

  number(
    label: Mithril.Children,
    key: 'days' | 'minPosts' | 'minAgeDays' | 'grandWinners',
    min: number,
    max: number,
    disabled = false
  ): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <input
          type="number"
          className="FormControl"
          min={min}
          max={max}
          value={this.form[key]}
          disabled={disabled}
          oninput={(e: Event) => (this.form[key] = parseInt((e.target as HTMLInputElement).value, 10) || min)}
        />
      </div>
    );
  }

  /**
   * The forum's own editor (Markdown, BBCode, Scribe...), as posts are written
   * in: the server formats these with the post formatter. Core's TextEditor
   * writes `composer.editor` when it builds, so each one gets a host object.
   */
  editor(key: string, get: () => string, set: (v: string) => void): Mithril.Children {
    this.hosts[key] ??= {};

    // Keyed, so another door gets a fresh editor rather than this one's text;
    // alone in its own list, as Mithril won't mix keyed and unkeyed siblings.
    return [
      <div className="GiveawayFormModal-editor" key={key}>
        <TextEditor composer={this.hosts[key]} value={get()} disabled={this.loading} onchange={(v: string) => set(v)} onsubmit={() => {}} />
      </div>,
    ];
  }

  hosts: Record<string, { editor?: unknown }> = {};

  area(label: Mithril.Children, get: () => string, set: (v: string) => void, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <textarea className="FormControl" rows={2} value={get()} oninput={(e: Event) => set((e.target as HTMLTextAreaElement).value)} />
        {help && <p className="helpText">{help}</p>}
      </div>
    );
  }

  /** Which button submitted: publish (or save) vs keep as a draft. */
  publish = true;

  submitAs(publish: boolean) {
    this.publish = publish;
  }

  onsubmit(e: Event) {
    e.preventDefault();
    this.loading = true;
    const days = Math.max(1, Math.min(31, this.form.days));

    saveCalendar(
      {
        ...this.form,
        days,
        publish: this.publish || this.started(),
        doors: this.doors.slice(0, days),
      },
      this.attrs.calendar?.id
    )
      .then((calendar) => {
        this.loading = false;
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.advent.builder.saved'));
        this.hide();
        this.attrs.onsave?.(calendar);
      })
      .catch((err) => {
        this.loading = false;
        this.onerror(err);
      });
  }

  remove() {
    const c = this.attrs.calendar;
    if (!c || !confirm(extractText(app.translator.trans('ernestdefoe-giveaways.forum.advent.builder.delete_confirm')))) return;

    deleteCalendar(c.id)
      .then(() => {
        this.hide();
        this.attrs.onsave?.(null);
      })
      .catch(() => {
        /* app.request already showed the alert */
      });
  }
}
