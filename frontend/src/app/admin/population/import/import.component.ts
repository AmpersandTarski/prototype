import {
  Component,
  ViewChild,
  ChangeDetectorRef,
  AfterViewInit,
  OnDestroy,
} from '@angular/core';
import { HttpEventType } from '@angular/common/http';
import {
  FileUpload,
  FileUploadHandlerEvent,
  FileSelectEvent,
} from 'primeng/fileupload';
import { PopulationService } from '../population.service';
import { Subscription } from 'rxjs';
import { MessageService } from 'primeng/api';

type UploadStatus = 'queued' | 'uploading' | 'done' | 'error' | 'canceled';

interface SelectedFileItem {
  file: File;
  name: string;
  size: number;
  progress: number; // 0..100
  status: UploadStatus;
  errorMessage?: string;
}

@Component({
  selector: 'app-population-import',
  templateUrl: './import.component.html',
  styleUrls: ['./import.component.scss'],
})
export class ImportComponent implements AfterViewInit, OnDestroy {
  /**
   * ImportComponent allows the user to upload files containing population data.
   * The CANCEL_ALL_LABEL constant is used both in the HTML template and in the DOM selector
   * logic within updateCancelButtonState() to ensure consistency.
   */
  @ViewChild('uploader') private uploader: FileUpload;

  // Constant for Cancel All button text - used in template and DOM selector
  public readonly CANCEL_ALL_LABEL = 'Cancel all';

  // Selected files shown above the uploader with per-file progress
  public selectedFiles: SelectedFileItem[] = [];
  private uploadSubs: Map<string, Subscription> = new Map();

  private key(f: File): string {
    return `${f.name}|${f.size}`;
  }

  constructor(
    private populationService: PopulationService,
    private cd: ChangeDetectorRef,
    private messageService: MessageService,
  ) {}

  ngAfterViewInit(): void {
    // Initialize cancel button state once the view is ready
    setTimeout(() => {
      this.updateCancelButtonState();
    }, 0);
  }

  ngOnDestroy(): void {
    // Check if there are pending imports to cancel
    const pendingImports = this.selectedFiles.filter(
      (file) => file.status === 'queued' || file.status === 'uploading',
    );

    if (pendingImports.length > 0) {
      // Cancel all ongoing imports
      this.uploadSubs.forEach((sub) => sub.unsubscribe());
      this.uploadSubs.clear();

      // Update file statuses to canceled
      pendingImports.forEach((file) => {
        file.status = 'canceled';
        file.progress = 0;
      });

      // Show warning toast with specific format based on number of files
      let detail: string;
      if (pendingImports.length <= 3) {
        const fileNames = pendingImports.map((f) => f.name);
        if (fileNames.length === 1) {
          detail = `Import of ${fileNames[0]} has been cancelled.`;
        } else if (fileNames.length === 2) {
          detail = `Import of ${fileNames[0]} and ${fileNames[1]} have been cancelled.`;
        } else {
          detail = `Import of ${fileNames[0]}, ${fileNames[1]} and ${fileNames[2]} have been cancelled.`;
        }
      } else {
        detail = `${pendingImports.length} Imports cancelled.`;
      }

      this.messageService.add({
        severity: 'warn',
        summary: 'Imports Cancelled',
        detail: detail,
        sticky: true,
      });
    } else {
      // Still clean up any remaining subscriptions
      this.uploadSubs.forEach((sub) => sub.unsubscribe());
      this.uploadSubs.clear();
    }
  }

  private updateCancelButtonState(): void {
    if (!this.uploader) return;

    // Use setTimeout to ensure DOM is ready and find the Cancel All button

    // Find all p-button elements within p-fileupload
    const pButtons = document.querySelectorAll('p-fileupload p-button');
    let cancelPButton = null;

    for (const pButton of Array.from(pButtons)) {
      // Look for button > span containing "Cancel all"
      const buttonElement = pButton.querySelector('button');
      if (buttonElement) {
        const spanElement = buttonElement.querySelector('span');
        if (
          spanElement &&
          spanElement.textContent?.includes(this.CANCEL_ALL_LABEL)
        ) {
          cancelPButton = pButton;
          break;
        }
      }
    }

    if (cancelPButton) {
      const shouldDisable = this.allDoneOrEmpty;

      // Apply p-disabled to the p-button element
      if (shouldDisable) {
        cancelPButton.classList.add('p-disabled');
      } else {
        cancelPButton.classList.remove('p-disabled');
      }
    }
  }

  // Add files to the visual list (in 'queued' state)
  public onSelect(event: FileSelectEvent): void {
    const newlyAdded: File[] = [];
    for (const f of event.files ?? []) {
      const exists = this.selectedFiles.some(
        (it) => it.name === f.name && it.size === f.size,
      );

      // ignore if the selected file is already in the list
      if (!exists) {
        this.selectedFiles.push({
          file: f,
          name: f.name,
          size: f.size,
          progress: 0,
          status: 'queued',
        });
        newlyAdded.push(f);
      }
    }

    // auto-start uploads sequentially for just-selected files
    (async () => {
      for (const f of newlyAdded) {
        await new Promise((resolve) => this.upload(f, resolve));
      }
    })();

    this.cd.markForCheck();
  }

  // Clear the visual list as well (also cancel in-flight uploads)
  public onClear(): void {
    this.uploadSubs.forEach((sub) => sub.unsubscribe());
    this.uploadSubs.clear();
    this.selectedFiles = [];
    if (this.uploader) {
      this.uploader.progress = 0;
    }
  }

  public async handleUpload(event: FileUploadHandlerEvent): Promise<void> {
    // Iterate over the files user just triggered upload for
    for (const file of event.files) {
      await new Promise((resolve) => this.upload(file, resolve));
    }

    // clean up uploader state
    if (this.uploader) {
      this.uploader.uploading = false;
      this.uploader.progress = 0;
      this.uploader.clear();
    }
  }

  private upload(file: File, resolve: (value: unknown) => void): void {
    const uploader = this.uploader;
    const item = this.selectedFiles.find(
      (it) => it.name === file.name && it.size === file.size,
    );
    if (item) {
      item.status = 'uploading';
      // start with a minimal value so the bar appears immediately
      item.progress = 1;
      // ensure UI reflects status switch right away
      this.cd.markForCheck();
      if (uploader && uploader.cd) {
        uploader.cd.markForCheck();
      }
    }

    const key = this.key(file);
    const sub = this.populationService.importPopulation(file).subscribe({
      next: (event) => {
        switch (event.type) {
          case HttpEventType.UploadProgress: {
            if (event.loaded && typeof event.total === 'number') {
              // show the progress separate for each file,
              // starting all over again from 0 on the next file
              if (uploader) {
                uploader.progress = Math.round(
                  (event.loaded * 100) / event.total,
                );
              }
              if (item) {
                item.progress = Math.round((event.loaded * 100) / event.total);
              }
            }
            break;
          }
          case HttpEventType.Response:
            // Only push on actual success
            if (uploader && event.status === 200) {
              if (item) {
                item.progress = 100;
                item.status = 'done';
              }
              uploader.uploadedFiles.push(file);
              // Update cancel button state when file is done
              this.updateCancelButtonState();
            }
            break;
        }
        // Mark both PrimeNG uploader and this component for change detection
        if (uploader && uploader.cd) {
          uploader.cd.markForCheck();
        }
        this.cd.markForCheck();
      },
      error: (error) => {
        // Do not mark success on error; reset/flag for this file
        if (item) {
          item.status = 'error';
          // Extract error message from rich HTTP error response
          item.errorMessage = this.extractErrorMessage(error, item.name);
        }
        if (uploader) {
          uploader.progress = 0;
          if (uploader.cd) uploader.cd.markForCheck();
        }
        this.cd.markForCheck();
        // cleanup subscription bookkeeping
        if (this.uploadSubs.has(key)) this.uploadSubs.delete(key);
        // Continue with next file
        resolve(true);
      },
      complete: () => {
        // cleanup subscription bookkeeping
        if (this.uploadSubs.has(key)) this.uploadSubs.delete(key);
        this.cd.markForCheck();
        // by resolving here, we always continue with the next file, even on a failed upload or import error
        resolve(true);
      },
    });
    // remember subscription for potential cancellation
    this.uploadSubs.set(key, sub);
    // Also resolve the awaiting Promise if this subscription is unsubscribed (e.g., canceled)
    sub.add(() => {
      if (this.uploadSubs.has(key)) {
        this.uploadSubs.delete(key);
      }
      // Ensure the sequential chain continues even on cancel
      try {
        resolve(true);
      } catch {}
      this.cd.markForCheck();
    });
  }

  public cancelItem(item: SelectedFileItem): void {
    const k = this.key(item.file);
    const sub = this.uploadSubs.get(k);
    if (sub) {
      sub.unsubscribe();
      this.uploadSubs.delete(k);
    }
    // mark as canceled and keep in the list until the user clears it
    item.status = 'canceled';
    item.progress = 0;
    if (this.uploader && this.uploader.cd) {
      this.uploader.cd.markForCheck();
    }
    this.cd.markForCheck();
    // Update cancel button state when file is canceled
    this.updateCancelButtonState();
  }

  public clearItem(item: SelectedFileItem): void {
    // remove a canceled item from the list
    this.selectedFiles = this.selectedFiles.filter((it) => it !== item);
    if (this.uploader && this.uploader.cd) {
      this.uploader.cd.markForCheck();
    }
    this.cd.markForCheck();
    // Update cancel button state when file is cleared
    this.updateCancelButtonState();
  }

  public retryItem(item: SelectedFileItem): void {
    if (!item || (item.status !== 'error' && item.status !== 'canceled'))
      return;
    // restart upload for this item
    item.status = 'uploading';
    item.progress = 1;
    this.cd.markForCheck();
    // run this upload independently
    // eslint-disable-next-line @typescript-eslint/no-floating-promises
    new Promise((resolve) => this.upload(item.file, resolve));
  }

  get allDoneOrEmpty(): boolean {
    return (
      this.selectedFiles.length === 0 ||
      this.selectedFiles.every(
        (it) => it.status === 'done' || it.status === 'canceled',
      )
    );
  }

  get fileUploadClasses(): string {
    const cls: string[] = ['hide-overall-progress'];
    if (this.allDoneOrEmpty) cls.push('cancel-disabled');
    return cls.join(' ');
  }

  public formatSize(bytes: number): string {
    if (!bytes && bytes !== 0) return '';
    const thresh = 1024;
    if (bytes < thresh) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let u = -1;
    do {
      bytes /= thresh;
      ++u;
    } while (bytes >= thresh && u < units.length - 1);
    return `${bytes.toFixed(1)} ${units[u]}`;
  }

  /**
   * Extract meaningful error message from HTTP error response
   */
  private extractErrorMessage(error: any, fileName: string): string {
    try {
      // Priority 1: Check for structured error response with msg
      if (error?.error?.msg) {
        const msg = error.error.msg;
        // The backend message already contains cell references, so use it directly
        return msg;
      }

      // Priority 2: Check for generic error.error field
      if (error?.error?.error) {
        return `Error in file "${fileName}": ${error.error.error}`;
      }

      // Priority 3: Check for standard HTTP error message
      if (error?.message) {
        return `Error in file "${fileName}": ${error.message}`;
      }

      // Priority 4: Check for error status with details
      if (error?.status) {
        const statusText = error.statusText ? ` (${error.statusText})` : '';
        return `HTTP Error ${error.status}${statusText} in file "${fileName}"`;
      }

      // Fallback: Generic error message
      return `Upload failed for file "${fileName}". Please try again.`;
    } catch (e) {
      // Safety fallback if error parsing fails
      return `Upload failed for file "${fileName}". Please try again.`;
    }
  }
}
