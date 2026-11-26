import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type { AdventDoor as Door } from '../../common/advent';

export interface AdventDoorAttrs {
  door: Door;
  selected: boolean;
  /** Which of the four door colours to paint it. */
  tint: number;
  onclick: () => void;
}

const ICONS: Record<string, string> = { prize: 'fas fa-gift', reveal: 'fas fa-star', exchange: 'fas fa-mitten' };

/**
 * One door, drawn as a little panelled front door with snow on its lintel.
 * The leaf swings open on a hinge once the member opens it; behind it is the
 * door's kind. The colours come from the forum's primary colour (see
 * .AdventGrid in forum.less), so themes and Festoon recolour it.
 */
export default class AdventDoor extends Component<AdventDoorAttrs> {
  view(): Mithril.Children {
    const { door, selected, tint, onclick } = this.attrs;
    const label = extractText(
      app.translator.trans(`ernestdefoe-giveaways.forum.advent.door_label.${door.state}`, {
        day: door.day,
        date: dayjs(door.opensAt).format('LL'),
      })
    );

    return (
      <button
        type="button"
        className={`AdventDoor AdventDoor--${door.state} AdventDoor--tint${tint}` + (selected ? ' is-selected' : '')}
        aria-label={label}
        title={label}
        aria-pressed={selected}
        onclick={onclick}
      >
        <span className="AdventDoor-inside" aria-hidden="true">
          {door.state === 'opened' && <Icon name={ICONS[door.kind || 'reveal']} />}
        </span>
        <span className="AdventDoor-leaf" aria-hidden="true">
          <svg className="AdventDoor-art" viewBox="0 0 60 80" preserveAspectRatio="none">
            <rect className="AdventDoor-panelBg" x="0" y="0" width="60" height="80" rx="5" />
            <rect className="AdventDoor-inset" x="9" y="13" width="42" height="24" rx="3" />
            <rect className="AdventDoor-inset" x="9" y="43" width="42" height="28" rx="3" />
            <circle className="AdventDoor-knob" cx="49" cy="40" r="2.4" />
            <path
              className="AdventDoor-snow"
              d="M0 5 Q0 0 5 0 H55 Q60 0 60 5 V7 Q57 10 54 7 Q50 12 46 7 Q42 10 37 7 Q33 13 28 7 Q24 10 19 7 Q15 11 11 7 Q7 10 4 7 Q2 9 0 7 Z"
            />
          </svg>
          <span className="AdventDoor-number">{door.day}</span>
          {door.state === 'locked' && <Icon name="fas fa-lock" className="AdventDoor-lock" />}
        </span>
      </button>
    );
  }
}
