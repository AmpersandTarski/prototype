// The component imports Angular libraries that are compiled when they are loaded.
import '@angular/compiler';
import { of } from 'rxjs';
import { TransactionBarComponent } from './transaction-bar.component';
import { TransactionalInterface } from '../../shared/services/transaction.service';

describe('TransactionBarComponent', () => {
  let location: { back: jest.Mock };
  let router: { lastSuccessfulNavigation: unknown };

  /** An interface whose save commits (the buffer is empty afterwards) or is refused. */
  function anInterface(options: {
    committed: boolean;
    forOneRecord: boolean;
  }): TransactionalInterface {
    let dirty = true;
    return {
      isDirty: () => dirty,
      canSave: () => true,
      violations: () => [],
      save: () => {
        dirty = !options.committed;
        return of(null);
      },
      cancel: jest.fn(),
      returnsWhenDone: () => options.forOneRecord,
    };
  }

  function bar(): TransactionBarComponent {
    const txn = { active$: of(null) };
    return new TransactionBarComponent(
      txn as any,
      router as any,
      location as any,
    );
  }

  beforeEach(() => {
    location = { back: jest.fn() };
    // The user came from another screen of the application.
    router = { lastSuccessfulNavigation: { previousNavigation: {} } };
  });

  it('returns to where the user came from after a save of a screen for one record', () => {
    bar().save(anInterface({ committed: true, forOneRecord: true }));
    expect(location.back).toHaveBeenCalledTimes(1);
  });

  it('stays when the save is refused, so that the user can repair the edits', () => {
    bar().save(anInterface({ committed: false, forOneRecord: true }));
    expect(location.back).not.toHaveBeenCalled();
  });

  it('stays on a screen that is not for one record', () => {
    bar().save(anInterface({ committed: true, forOneRecord: false }));
    expect(location.back).not.toHaveBeenCalled();
  });

  it('returns to where the user came from after a cancel of a screen for one record', () => {
    const ifc = anInterface({ committed: false, forOneRecord: true });
    bar().cancel(ifc);
    expect(ifc.cancel).toHaveBeenCalledTimes(1);
    expect(location.back).toHaveBeenCalledTimes(1);
  });

  it('stays after a cancel on a screen that is not for one record', () => {
    bar().cancel(anInterface({ committed: false, forOneRecord: false }));
    expect(location.back).not.toHaveBeenCalled();
  });

  it('stays when the user opened the screen directly, with no screen to return to', () => {
    router = { lastSuccessfulNavigation: { previousNavigation: null } };
    bar().save(anInterface({ committed: true, forOneRecord: true }));
    expect(location.back).not.toHaveBeenCalled();
  });
});
