import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import TextEditor from 'flarum/common/components/TextEditor';

import { saveGiveaway, listCategories, showGiveaway } from '../../common/api';
import { hasMediaLibrary, pickImage } from '../utils/mediaLibrary';
import type { Giveaway, GiveawayCategory } from '../../common/api';

export interface GiveawayFormAttrs extends IInternalModalAttrs {
  giveaway?: Giveaway;
  categories?: GiveawayCategory[];
  /** Opened from the composer: saved as a draft that goes public with the post. */
  fromComposer?: boolean;
  onsave?: (giveaway: Giveaway) => void;
}

function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toIso(local: string): string | null {
  if (!local) return null;
  const d = new Date(local);
  return isNaN(d.getTime()) ? null : d.toISOString();
}

export default class GiveawayFormModal extends FormModal<GiveawayFormAttrs> {
  titleInput!: Stream<string>;
  prize!: Stream<string>;
  description!: Stream<string>;
  rules!: Stream<string>;
  skillQuestion!: Stream<string>;
  skillAnswer!: Stream<string>;
  skillAttempts!: Stream<number>;
  coverUrl!: Stream<string>;
  endsAt!: Stream<string>;
  startsAt!: Stream<string>;
  winnerCount!: Stream<number>;
  postBonus!: Stream<number>;
  minPosts!: Stream<number>;
  minAgeDays!: Stream<number>;
  categoryId!: Stream<number>;
  claimInstructions!: Stream<string>;
  claimLines!: Stream<string>;
  categories: GiveawayCategory[] = [];
  /** Set by the "Save as draft" button, read by onsubmit. */
  asDraft = false;
  /**
   * Core's TextEditor assigns `composer.editor` when it builds, with no guard,
   * and toolbar buttons (Markdown, Scribe, uploads, mentions) read it back.
   * Any object with an `editor` slot will do; one per editor.
   */
  descriptionHost: { editor?: unknown } = {};
  rulesHost: { editor?: unknown } = {};

  oninit(vnode: Mithril.Vnode<GiveawayFormAttrs>) {
    super.oninit(vnode);
    const g = this.attrs.giveaway;
    this.categories = this.attrs.categories || [];
    if (!this.categories.length) {
      listCategories().then((res) => {
        this.categories = res.data || [];
        m.redraw();
      });
    }
    this.categoryId = Stream(g?.category?.id || 0);
    this.claimInstructions = Stream(g?.claimInstructions || '');
    this.claimLines = Stream(g?.claimLines || '');
    this.titleInput = Stream(g?.title || '');
    this.prize = Stream(g?.prize || '');
    this.description = Stream(g?.description || '');
    this.rules = Stream(g?.rules || '');
    this.skillQuestion = Stream(g?.skillQuestion || '');
    this.skillAnswer = Stream(g?.skillAnswer || '');
    this.skillAttempts = Stream(g?.skillAttempts || 0);
    this.coverUrl = Stream(g?.coverUrl || '');
    this.endsAt = Stream(toLocalInput(g?.endsAt));
    this.startsAt = Stream(toLocalInput(g?.startsAt));
    this.winnerCount = Stream(g?.winnerCount || 1);
    this.postBonus = Stream(g?.postBonus || 0);
    this.minPosts = Stream(g?.minPosts || 0);
    this.minAgeDays = Stream(g?.minAgeDays || 0);
  }

  className() {
    return 'GiveawayFormModal Modal--large';
  }

  title() {
    return this.attrs.giveaway
      ? app.translator.trans('ernestdefoe-giveaways.forum.edit')
      : this.attrs.fromComposer
        ? app.translator.trans('ernestdefoe-giveaways.forum.composer.add')
        : app.translator.trans('ernestdefoe-giveaways.forum.create');
  }

  content(): Mithril.Children {
    const t = (k: string) => app.translator.trans('ernestdefoe-giveaways.forum.form.' + k);
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.field(
            t('title_label'),
            <input
              className="FormControl"
              value={this.titleInput()}
              placeholder={t('title_placeholder') as string}
              oninput={(e: Event) => this.titleInput((e.target as HTMLInputElement).value)}
            />
          )}
          {this.field(
            t('prize_label'),
            <input
              className="FormControl"
              value={this.prize()}
              placeholder={t('prize_placeholder') as string}
              oninput={(e: Event) => this.prize((e.target as HTMLInputElement).value)}
            />
          )}
          {this.field(t('description_label'), this.editor(this.descriptionHost, this.description, t('description_placeholder') as string))}
          {this.field(t('rules_label'), this.editor(this.rulesHost, this.rules, t('rules_placeholder') as string), t('rules_help'))}
          <div className="GiveawayFormModal-row">
            {this.field(
              t('skill_question_label'),
              <input
                className="FormControl"
                value={this.skillQuestion()}
                disabled={this.drawn()}
                placeholder={t('skill_question_placeholder') as string}
                oninput={(e: Event) => this.skillQuestion((e.target as HTMLInputElement).value)}
              />
            )}
            {this.field(
              t('skill_answer_label'),
              <input
                className="FormControl"
                value={this.skillAnswer()}
                disabled={this.drawn()}
                oninput={(e: Event) => this.skillAnswer((e.target as HTMLInputElement).value)}
              />
            )}
          </div>
          <p className="helpText GiveawayFormModal-skillHelp">{t('skill_answer_help')}</p>
          {this.numberField(t('skill_attempts_label'), this.skillAttempts, 0, t('skill_attempts_help'), this.drawn())}
          {this.field(
            t('cover_label'),
            <div className="GiveawayFormModal-cover">
              <input
                className="FormControl"
                value={this.coverUrl()}
                placeholder={t('cover_placeholder') as string}
                oninput={(e: Event) => this.coverUrl((e.target as HTMLInputElement).value)}
              />
              {hasMediaLibrary() && (
                <Button className="Button" icon="fas fa-photo-video" onclick={() => pickImage((url) => this.coverUrl(url))}>
                  {t('cover_pick')}
                </Button>
              )}
            </div>
          )}
          {this.categories.length > 0 &&
            this.field(
              t('category_label'),
              <select
                className="FormControl"
                value={this.categoryId()}
                onchange={(e: Event) => this.categoryId(parseInt((e.target as HTMLSelectElement).value, 10) || 0)}
              >
                <option value={0}>{t('category_none')}</option>
                {this.categories.map((c) => (
                  <option value={c.id} selected={this.categoryId() === c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            )}

          <div className="GiveawayFormModal-row">
            {this.field(
              t('starts_label'),
              <input
                type="datetime-local"
                className="FormControl"
                value={this.startsAt()}
                oninput={(e: Event) => this.startsAt((e.target as HTMLInputElement).value)}
              />
            )}
            {this.field(
              t('ends_label'),
              <input
                type="datetime-local"
                className="FormControl"
                value={this.endsAt()}
                disabled={this.drawn()}
                oninput={(e: Event) => this.endsAt((e.target as HTMLInputElement).value)}
              />
            )}
          </div>

          <div className="GiveawayFormModal-row">
            {this.numberField(t('winner_count_label'), this.winnerCount, 1, undefined, this.drawn())}
            {this.numberField(t('post_bonus_label'), this.postBonus, 0, t('post_bonus_help'))}
          </div>
          <div className="GiveawayFormModal-row">
            {this.numberField(t('min_posts_label'), this.minPosts, 0)}
            {this.numberField(t('min_age_label'), this.minAgeDays, 0)}
          </div>
          {this.field(
            t('claim_label'),
            <textarea
              className="FormControl"
              rows={3}
              value={this.claimInstructions()}
              placeholder={t('claim_placeholder') as string}
              oninput={(e: Event) => this.claimInstructions((e.target as HTMLTextAreaElement).value)}
            />,
            t('claim_help')
          )}
          {this.field(
            t('claim_lines_label'),
            <textarea
              className="FormControl GiveawayFormModal-claimLines"
              rows={4}
              value={this.claimLines()}
              placeholder={t('claim_lines_placeholder') as string}
              oninput={(e: Event) => this.claimLines((e.target as HTMLTextAreaElement).value)}
            />,
            t('claim_lines_help')
          )}

          <div className="Form-group GiveawayFormModal-actions">
            <Button
              type="submit"
              className="Button Button--primary"
              loading={this.loading && !this.asDraft}
              onclick={() => {
                // A handler returning false would cancel the submit (Mithril).
                this.asDraft = false;
              }}
            >
              {this.attrs.fromComposer && !this.attrs.giveaway ? t('add_to_post') : t('submit')}
            </Button>
            {!this.attrs.giveaway && !this.attrs.fromComposer && (
              <Button
                type="submit"
                className="Button"
                icon="far fa-file"
                loading={this.loading && this.asDraft}
                onclick={() => {
                  this.asDraft = true;
                }}
              >
                {t('save_draft')}
              </Button>
            )}
          </div>
          {this.attrs.fromComposer && <p className="helpText">{t('composer_help')}</p>}
        </div>
      </div>
    );
  }

  /** The forum's own post editor, so the text formats exactly as a post does. */
  editor(host: { editor?: unknown }, stream: Stream<string>, placeholder: string): Mithril.Children {
    return (
      <div className="GiveawayFormModal-editor">
        <TextEditor
          composer={host}
          value={stream()}
          placeholder={placeholder}
          disabled={this.loading}
          onchange={(v: string) => stream(v)}
          onsubmit={() => {}}
        />
      </div>
    );
  }

  field(label: Mithril.Children, control: Mithril.Children, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        {control}
        {help && <p className="helpText">{help}</p>}
      </div>
    );
  }

  /** The terms a draw was made and judged under are fixed once it has run. */
  drawn(): boolean {
    return !!this.attrs.giveaway && this.attrs.giveaway.status !== 'active';
  }

  numberField(label: Mithril.Children, stream: Stream<number>, min: number, help?: Mithril.Children, disabled = false): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <input
          type="number"
          className="FormControl"
          min={min}
          value={stream()}
          disabled={disabled}
          oninput={(e: Event) => stream(parseInt((e.target as HTMLInputElement).value, 10) || 0)}
        />
        {help && <p className="helpText">{help}</p>}
      </div>
    );
  }

  onsubmit(e: Event) {
    e.preventDefault();
    this.loading = true;

    const attrs = {
      title: this.titleInput(),
      prize: this.prize(),
      description: this.description(),
      rules: this.rules(),
      skillQuestion: this.skillQuestion(),
      skillAnswer: this.skillAnswer(),
      skillAttempts: this.skillAttempts(),
      coverUrl: this.coverUrl(),
      endsAt: toIso(this.endsAt()),
      startsAt: toIso(this.startsAt()),
      winnerCount: this.winnerCount(),
      postBonus: this.postBonus(),
      minPosts: this.minPosts(),
      minAgeDays: this.minAgeDays(),
      categoryId: this.categoryId() || null,
      claimInstructions: this.claimInstructions(),
      claimLines: this.claimLines(),
      // New from the composer, or "Save as draft": only its maker sees it.
      ...(!this.attrs.giveaway && (this.attrs.fromComposer || this.asDraft) ? { draft: true } : {}),
    };

    saveGiveaway(attrs, this.attrs.giveaway?.id)
      .then((res) => {
        this.loading = false;
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-giveaways.forum.saved'));
        this.hide();
        this.attrs.onsave?.(res.data);
      })
      .catch((err) => {
        this.loading = false;
        this.onerror(err);
      });
  }
}

/** The link that ties a draft to its post: posting the post publishes the draft. */
function linkFor(slug: string): string {
  const url = app.forum.attribute<string>('baseUrl') + '/giveaways/' + encodeURIComponent(slug);
  // Scribe turns a Markdown link into a real one on insert; every other editor
  // autolinks a bare URL. On a line of its own, it shows as the giveaway's card.
  const scribe = 'ernestdefoe-scribe' in ((window as any).flarum?.extensions || {});
  return scribe ? `\n\n[${url}](${url})\n\n` : `\n\n${url}\n\n`;
}

/** Opened by the composer's "Add giveaway" / "Edit giveaway" button. */
export function openFromComposer(state: any): void {
  const attached: { id: number; slug: string } | undefined = state.fields.giveaway;

  const show = (giveaway?: Giveaway) =>
    app.modal.show(GiveawayFormModal, {
      giveaway,
      fromComposer: true,
      onsave: (saved: Giveaway) => {
        if (!attached) state.editor?.insertAtCursor(linkFor(saved.slug), false);
        state.fields.giveaway = { id: saved.id, slug: saved.slug };
        m.redraw();
      },
    });

  if (!attached) {
    show();
    return;
  }

  // Editing: the form needs the full giveaway (the description's source and all).
  showGiveaway(attached.id).then(
    (res) => show(res.data),
    () => show()
  );
}
