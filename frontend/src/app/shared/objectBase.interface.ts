// eslint-disable-next-line @typescript-eslint/ban-types
export type ObjectBase = Object;

export interface InterfaceRefObject {
  id: string;
  label: string;
}

export type Object = {
  _id_: string;
  _label_: string;
  _path_: string;
  _ifcs_: Array<InterfaceRef>;
};

export type InterfaceRef = {
  id: string;
  label: string;
};
