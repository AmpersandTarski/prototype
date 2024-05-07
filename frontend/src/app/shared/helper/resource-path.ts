type PathPart = string;

export class ResourcePath {
  readonly parts: PathPart[] = [];

  constructor(path: string) {
    this.parts = path.split('/').filter((x) => x !== '');
  }

  toString() {
    return this.parts.join('/');
  }

  /**
   * Init takes a list and returns everything except its last element.
   */
  init(): ResourcePath {
    return new ResourcePath(this.parts.slice(0, -1).join('/'));
  }

  /**
   * Head takes a list and returns its head. The head of a list is basically its first element.
   */
  head(): ResourcePath {
    return new ResourcePath(this.parts.slice(0, 1).join('/'));
  }

  /**
   * Returns the suffix of path after removing the prefix
   */
  drop(prefix: ResourcePath): ResourcePath {
    return new ResourcePath(this.toString().substring(prefix.toString().length));
  }

  prepend(path: ResourcePath): ResourcePath {
    return new ResourcePath([...path.parts, ...this.parts].join('/'));
  }

  append(path: ResourcePath): ResourcePath {
    return new ResourcePath([...this.parts, ...path.parts].join('/'));
  }
}
