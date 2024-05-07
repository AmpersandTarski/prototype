/** Check is object and not array */
export function isObject(item: any): boolean {
  return item && typeof item === 'object' && !Array.isArray(item);
}

/**
 * Deep merge two objects.
 * Used when patching (updating) from edit screens. The response object
 * is deep merged into the data we have.
 * This way, the objects aren't replaced, just updated. Then angular won't feel
 * the need to replace dom nodes, instead of updating them.
 */
export function mergeDeep(target: any, ...sources: any[]): any {
  if (!sources.length) return target;
  const source = sources.shift();

  if (isObject(target) && isObject(source)) {
    for (const key in source) {
      if (isObject(source[key])) {
        /* Object merging */

        if (!target[key]) {
          Object.assign(target, { [key]: {} });
        }

        mergeDeep(target[key], source[key]);
      } else if (Array.isArray(source[key])) {
        /* Deep array merging based on matching by item._id_ */

        if (!Array.isArray(target[key])) {
          target[key] = [];
        }

        const seenTargetChildren = [];
        for (const sourceChild of source[key]) {
          let targetChild = undefined;
          if (sourceChild._id_) {
            targetChild = target[key].find((tc: any) => tc._id_ == sourceChild._id_);
          }

          if (targetChild) {
            mergeDeep(targetChild, sourceChild);
            seenTargetChildren.push(targetChild);

            // Move targetChild to end, so that we'll end up with the same
            // sorting as source array
            target[key].push(target[key].splice(target[key].indexOf(targetChild), 1)[0]);
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
        /* Not object or array? Just assign */

        Object.assign(target, { [key]: source[key] });
      }
    }
  }

  return mergeDeep(target, ...sources);
}
