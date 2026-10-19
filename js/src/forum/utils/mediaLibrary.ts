import app from 'flarum/forum/app';

/**
 * fof/upload's own media manager, when that extension is enabled and the
 * viewer may upload. Nothing is bundled from it: its modal and uploader are
 * taken from Flarum's export registry at click time, so without fof/upload the
 * cover field is the plain URL input it always was.
 */
function modules(): { Modal: any; Uploader: any } | null {
  const reg = (window as any).flarum?.reg;
  if (!reg || !app.forum.attribute('fof-upload.canUpload')) return null;

  const Modal = reg.checkModule('fof-upload', 'forum/components/FileManagerModal');
  const Uploader = reg.checkModule('fof-upload', 'forum/handler/Uploader');

  return Modal && Uploader ? { Modal: Modal.default || Modal, Uploader: Uploader.default || Uploader } : null;
}

export function hasMediaLibrary(): boolean {
  return modules() !== null;
}

/** Opens the media manager over the current modal; `onPick` gets the chosen image's URL. */
export function pickImage(onPick: (url: string) => void): void {
  const mods = modules();
  if (!mods) return;

  app.modal.show(
    mods.Modal,
    {
      uploader: new mods.Uploader(),
      multiSelect: false,
      restrictFileType: 'image',
      onSelect: (ids: string[]) => {
        const file: any = app.store.getById('files', ids[0]) || app.store.getById('shared-files', ids[0]);
        const url = file?.url?.();
        if (url) {
          onPick(url);
          m.redraw();
        }
      },
    },
    true
  );
}
