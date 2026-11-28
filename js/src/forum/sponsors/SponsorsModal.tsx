import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import {
  createReportLink,
  deleteSponsor,
  eraseOptIns,
  exportOptIns,
  listSponsors,
  revokeReportLink,
  saveSponsor,
  showSponsor,
} from '../../common/sponsors';
import type { Sponsor } from '../../common/sponsors';

type Form = { name: string; logoUrl: string; websiteUrl: string; privacyUrl: string; description: string };

const blank = (): Form => ({ name: '', logoUrl: '', websiteUrl: '', privacyUrl: '', description: '' });

/**
 * Managers' sponsors: add and edit them, make or turn off the secret report
 * link, download the opted-in members, erase their opt-ins.
 */
export default class SponsorsModal extends FormModal<IInternalModalAttrs> {
  sponsors: Sponsor[] | null = null;
  editing: Sponsor | null = null;
  form: Form | null = null;
  /** A report link just made: shown once. */
  reportUrl: string | null = null;

  oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>) {
    super.oninit(vnode);
    this.reload();
  }

  className() {
    return 'SponsorsModal Modal--large';
  }

  title() {
    return this.t('manage_title');
  }

  t(key: string, params = {}) {
    return app.translator.trans(`ernestdefoe-giveaways.forum.sponsors.${key}`, params);
  }

  reload() {
    listSponsors()
      .then((list) => (this.sponsors = list))
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  content(): Mithril.Children {
    return <div className="Modal-body">{this.form ? this.editor() : this.list()}</div>;
  }

  list(): Mithril.Children {
    if (!this.sponsors) return <LoadingIndicator />;

    return [
      <ul className="SponsorsModal-list">
        {this.sponsors.map((s) => (
          <li className="SponsorsModal-row">
            <span className="SponsorsModal-name">{s.name}</span>
            <Button className="Button Button--sm" icon="fas fa-pencil-alt" onclick={() => this.edit(s)}>
              {this.t('edit')}
            </Button>
          </li>
        ))}
        {!this.sponsors.length && <li className="helpText">{this.t('none')}</li>}
      </ul>,
      <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.edit(null)}>
        {this.t('add')}
      </Button>,
    ];
  }

  edit(s: Sponsor | null) {
    this.reportUrl = null;
    if (!s) {
      this.editing = null;
      this.form = blank();
      return;
    }
    showSponsor(s.id)
      .then((full) => {
        this.editing = full;
        this.form = {
          name: full.name,
          logoUrl: full.logoUrl ?? '',
          websiteUrl: full.websiteUrl,
          privacyUrl: full.privacyUrl,
          description: full.description ?? '',
        };
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  editor(): Mithril.Children {
    const f = this.form!;
    const field = (key: keyof Form, label: string, help?: string, area = false) => (
      <div className="Form-group">
        <label>{this.t(label)}</label>
        {area ? (
          <textarea className="FormControl" rows={3} value={f[key]} oninput={(e: Event) => (f[key] = (e.target as HTMLTextAreaElement).value)} />
        ) : (
          <input className="FormControl" value={f[key]} oninput={(e: Event) => (f[key] = (e.target as HTMLInputElement).value)} />
        )}
        {help && <p className="helpText">{this.t(help)}</p>}
      </div>
    );

    return (
      <div className="Form">
        {field('name', 'name')}
        {field('logoUrl', 'logo')}
        {field('websiteUrl', 'website', 'website_help')}
        {field('privacyUrl', 'privacy', 'privacy_help')}
        {field('description', 'description', undefined, true)}
        <div className="Form-group SponsorsModal-actions">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {this.t('save')}
          </Button>
          <Button className="Button" onclick={() => (this.form = null)}>
            {this.t('back')}
          </Button>
          {this.editing && (
            <Button className="Button Button--danger" onclick={() => this.remove()}>
              {this.t('delete')}
            </Button>
          )}
        </div>
        {this.editing && this.privacyTools(this.editing)}
      </div>
    );
  }

  /** The report link, the export and the erase button. */
  privacyTools(s: Sponsor): Mithril.Children {
    return (
      <section className="SponsorsModal-privacy">
        <h4>{this.t('report_heading')}</h4>
        <p className="helpText">{this.t('report_help')}</p>
        {this.reportUrl && (
          <input
            className="FormControl SponsorsModal-reportUrl"
            readonly
            value={this.reportUrl}
            onfocus={(e: Event) => (e.target as HTMLInputElement).select()}
          />
        )}
        <div className="SponsorsModal-actions">
          <Button className="Button" icon="fas fa-link" onclick={() => this.makeReportLink(s)}>
            {this.t(s.reportLinkCreatedAt ? 'report_new' : 'report_make')}
          </Button>
          {s.reportLinkCreatedAt && (
            <Button className="Button" icon="fas fa-unlink" onclick={() => this.revokeReport(s)}>
              {this.t('report_off')}
            </Button>
          )}
        </div>
        <h4>{this.t('optins_heading', { count: s.optInCount ?? 0 })}</h4>
        <p className="helpText">{this.t(s.canExport ? 'optins_help' : 'optins_help_no_export')}</p>
        <div className="SponsorsModal-actions">
          {s.canExport && (
            <Button
              className="Button"
              icon="fas fa-file-csv"
              onclick={() =>
                exportOptIns(s).catch(() => {
                  /* app.request already showed the alert */
                })
              }
            >
              {this.t('export')}
            </Button>
          )}
          <Button className="Button Button--danger" icon="fas fa-eraser" onclick={() => this.erase(s)}>
            {this.t('erase')}
          </Button>
        </div>
      </section>
    );
  }

  makeReportLink(s: Sponsor) {
    if (s.reportLinkCreatedAt && !confirm(extractText(this.t('report_new_confirm')))) return;
    createReportLink(s.id)
      .then((url) => {
        this.reportUrl = url;
        s.reportLinkCreatedAt = new Date().toISOString();
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  revokeReport(s: Sponsor) {
    revokeReportLink(s.id)
      .then(() => {
        s.reportLinkCreatedAt = null;
        this.reportUrl = null;
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  erase(s: Sponsor) {
    if (!confirm(extractText(this.t('erase_confirm', { sponsor: s.name })))) return;
    eraseOptIns(s.id)
      .then(() => {
        s.optInCount = 0;
        app.alerts.show({ type: 'success' }, this.t('erased'));
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => m.redraw());
  }

  remove() {
    const s = this.editing;
    if (!s || !confirm(extractText(this.t('delete_confirm', { sponsor: s.name })))) return;
    deleteSponsor(s.id)
      .then(() => {
        this.form = null;
        this.reload();
      })
      .catch(() => {
        /* app.request already showed the alert */
      });
  }

  onsubmit(e: Event) {
    e.preventDefault();
    if (!this.form) return;
    this.loading = true;
    saveSponsor(this.form, this.editing?.id)
      .then(() => {
        this.form = null;
        app.alerts.show({ type: 'success' }, this.t('saved'));
        this.reload();
      })
      .catch((err) => this.onerror(err))
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
