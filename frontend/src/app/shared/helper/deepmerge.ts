/** Check is object and not array */
export function isObject(item: any): boolean {
  return item && typeof item === 'object' && !Array.isArray(item);
}

/**
 * Predicate deciding whether target[key] must be PRESERVED instead of being
 * overwritten by the source value. Used to protect a scalar field the user is
 * currently editing during a post-mutation server sync (see edit-registry.ts
 * and issue #298). Receives the object being merged into and the key at hand,
 * so an implementation can consult target._path_ + key.
 */
export type ProtectFn = (target: any, key: string) => boolean;

/**
 * Deep merge `source` into `target`.
 * Used when patching (updating) from edit screens. The response object
 * is deep merged into the data we have.
 * This way, the objects aren't replaced, just updated. Then angular won't feel
 * the need to replace dom nodes, instead of updating them.
 *
 * When `isProtected` is supplied, scalar values for which it returns true are
 * left untouched, so a field the user is actively editing keeps its typed value.
 */
export function mergeDeep(
  target: any,
  source: any,
  isProtected?: ProtectFn,
): any {
  if (isObject(target) && isObject(source)) {
    for (const key in source) {
      if (isObject(source[key])) {
        /* Object merging */

        if (!target[key]) {
          Object.assign(target, { [key]: {} });
        }

        mergeDeep(target[key], source[key], isProtected);
      } else if (Array.isArray(source[key])) {
        /* Deep array merging based on matching by item._id_ */

        if (!Array.isArray(target[key])) {
          target[key] = [];
        }

        const seenTargetChildren = [];
        for (const sourceChild of source[key]) {
          let targetChild = undefined;
          if (sourceChild._id_) {
            targetChild = target[key].find(
              (tc: any) => tc._id_ == sourceChild._id_,
            );
          }

          if (targetChild) {
            mergeDeep(targetChild, sourceChild, isProtected);
            seenTargetChildren.push(targetChild);

            // Move targetChild to end, so that we'll end up with the same
            // sorting as source array
            target[key].push(
              target[key].splice(target[key].indexOf(targetChild), 1)[0],
            );
          } else {
            // This `if` prevents duplicates when the array only has primitive values like strings
            if (!target[key].includes(sourceChild)) {
              target[key].push(sourceChild);
            }
            seenTargetChildren.push(sourceChild);
          }
        }

        // Remove items that weren't in source
        for (let i = target[key].length - 1; i >= 0; i--) {
          if (!seenTargetChildren.includes(target[key][i])) {
            target[key].splice(i, 1);
          }
        }
      } else {
        /* Not object or array? Just assign — unless the user is editing it */

        if (isProtected && isProtected(target, key)) {
          continue; // user is typing in this field: keep their value
        }
        Object.assign(target, { [key]: source[key] });
      }
    }
  }

  return target;
}
