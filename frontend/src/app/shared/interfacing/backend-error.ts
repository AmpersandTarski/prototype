import { HttpErrorResponse } from '@angular/common/http';
import { Notifications } from './notifications.interface';

type ErrorCode = number;

// Utility type to represent stringified JSON
type Stringified<T> = string;

export type HttpBackendErrorResponse = Omit<HttpErrorResponse, 'error'> & {
  error: BackendErrorResponse;
};

export type HttpBackendErrorResponseText = Omit<HttpErrorResponse, 'error'> & {
  error: Stringified<BackendErrorResponse>;
};

export type BackendErrorResponse = {
  error: ErrorCode;
  msg: string;
  html?: string;
  notifications: Notifications;
};
