/**
 * The part of node-diff3 that markdown-merge.ts uses, copied from node-diff3/src/diff3.d.ts of
 * version 3.2.1. node-diff3 names its types only under "exports" in its package.json, which
 * "moduleResolution": "node" in tsconfig.json does not read; a "paths" entry instead would also
 * send the bundler to the type file, and the bundle would lack the functions.
 */
declare module 'node-diff3' {
  export type ICommResult<T> =
    | { common: T[]; buffer1?: never; buffer2?: never }
    | { common?: never; buffer1: T[]; buffer2: T[] };

  export function diffComm<T>(buffer1: T[], buffer2: T[]): ICommResult<T>[];

  export interface MergeRegion<T> {
    ok?: T[];
    conflict?: {
      a: T[];
      aIndex: number;
      b: T[];
      bIndex: number;
      o: T[];
      oIndex: number;
    };
  }

  export interface IMergeOptions {
    excludeFalseConflicts?: boolean;
    stringSeparator?: string | RegExp;
  }

  export function diff3Merge<T = string>(
    a: string | T[],
    o: string | T[],
    b: string | T[],
    options?: IMergeOptions,
  ): MergeRegion<T>[];
}
