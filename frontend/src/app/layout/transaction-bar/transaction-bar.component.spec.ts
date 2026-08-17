import { CommonModule } from '@angular/common';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ButtonModule } from 'primeng/button';
import { BehaviorSubject } from 'rxjs';
import {
  TransactionService,
  TransactionalInterface,
  WorklistEntry,
} from '../../shared/services/transaction.service';
import { TransactionBarComponent } from './transaction-bar.component';

/**
 * The visible half of prototype#445: while a transaction is open the buffered invariant
 * violations read as work still to be done, and the entries the most recent edit brought
 * about are recognisable as such. See DesignChoices OK-11.
 */
describe('TransactionBarComponent — worklist', () => {
  let fixture: ComponentFixture<TransactionBarComponent>;
  let active$: BehaviorSubject<TransactionalInterface | null>;
  let worklist: WorklistEntry[];

  function activeInterface(canSave: boolean): TransactionalInterface {
    return {
      isDirty: () => true,
      canSave: () => canSave,
      worklist: () => worklist,
      save: () => new BehaviorSubject(null),
      cancel: () => undefined,
    };
  }

  const text = () => fixture.nativeElement.textContent as string;
  const items = () =>
    Array.from(
      fixture.nativeElement.querySelectorAll('.transaction-worklist__item'),
    ) as HTMLElement[];

  beforeEach(async () => {
    worklist = [];
    active$ = new BehaviorSubject<TransactionalInterface | null>(null);
    await TestBed.configureTestingModule({
      declarations: [TransactionBarComponent],
      imports: [CommonModule, ButtonModule],
      providers: [
        { provide: TransactionService, useValue: { active$ } },
      ],
    }).compileComponents();
    fixture = TestBed.createComponent(TransactionBarComponent);
  });

  it('shows no worklist while the buffered edits satisfy every rule', () => {
    active$.next(activeInterface(true));
    fixture.detectChanges();

    expect(text()).toContain('Unsaved changes');
    expect(text()).not.toContain('Before you can save');
    expect(items()).toHaveLength(0);
  });

  it('lists what still has to happen, in the register of guidance', () => {
    worklist = [
      { rule: 'A booking needs a guest name.', message: 'booking1 has no guest name.', isNew: false },
      { rule: 'A booking needs a guest name.', message: 'booking2 has no guest name.', isNew: true },
    ];
    active$.next(activeInterface(false));
    fixture.detectChanges();

    expect(text()).toContain('Before you can save');
    expect(items()).toHaveLength(2);
    expect(items()[0].textContent).toContain('booking1 has no guest name.');
    // The word "error" belongs to a violation reported outside a transaction, not here.
    expect(text().toLowerCase()).not.toContain('error');
  });

  it('marks the entry the most recent edit brought about', () => {
    worklist = [
      { rule: 'r', message: 'already open', isNew: false },
      { rule: 'r', message: 'just appeared', isNew: true },
    ];
    active$.next(activeInterface(false));
    fixture.detectChanges();

    const [old, fresh] = items();
    expect(old.classList).not.toContain('transaction-worklist__item--new');
    expect(old.textContent).not.toContain('from your last edit');
    expect(fresh.classList).toContain('transaction-worklist__item--new');
    expect(fresh.textContent).toContain('from your last edit');
  });

  it('disables SAVE while the worklist is not empty', () => {
    worklist = [{ rule: 'r', message: 'something to do', isNew: true }];
    active$.next(activeInterface(false));
    fixture.detectChanges();

    const save = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    ).find((b) => (b as HTMLButtonElement).textContent?.includes('Save')) as HTMLButtonElement;
    expect(save.disabled).toBe(true);
  });
});
