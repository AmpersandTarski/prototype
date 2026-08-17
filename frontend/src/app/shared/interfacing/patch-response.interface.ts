import { Notifications } from './notifications.interface';
import { Patch, PatchValue } from './patch.interface';
import { DryRunVerdictResponse } from './dry-run.interface';

export interface PatchResponse<T> extends DryRunVerdictResponse {
  content: T;
  patches: Array<Patch | PatchValue>;
  notifications: Notifications;
  invariantRulesHold: boolean;
  isCommitted: boolean;
  sessionRefreshAdvice: boolean;
  navTo: string | null;
}
