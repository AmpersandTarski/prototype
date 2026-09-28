import { Component, ElementRef } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { BaseBoxComponent } from './BaseBoxComponent.class';

@Component({ template: '' })
class TestBoxComponent extends BaseBoxComponent<any, any> {}

const item = (id: string) => ({
  _id_: id,
  _label_: id,
  _path_: `resource/SESSION/1/Things/${id}`,
  _ifcs_: [],
});

describe('BaseBoxComponent.createItem', () => {
  function setup(serverAppends: boolean) {
    TestBed.configureTestingModule({
      providers: [
        {
          provide: ElementRef,
          useValue: new ElementRef(document.createElement('div')),
        },
      ],
    });
    const box = TestBed.runInInjectionContext(() => new TestBoxComponent());
    const list = [item('A'), item('B')];
    box.resource = {
      _id_: '1',
      _label_: '1',
      _path_: 'resource/SESSION/1',
      _ifcs_: [],
      data: list,
    } as any;
    box.propertyName = 'Things';
    box.isRootBox = true;
    box.interfaceComponent = {
      // post() syncs with the server before the caller sees the response; for a list the
      // sync appends the new item (AmpersandInterfaceComponent.syncWithServer).
      post: () => {
        if (serverAppends) list.push(item('N'));
        return of({ content: item('N') });
      },
    } as any;
    return { box, list };
  }

  it('puts a created item once at the front, also when the sync already appended it', () => {
    const { box, list } = setup(true);
    box.createItem();
    expect(list.map((i) => i._id_)).toEqual(['N', 'A', 'B']);
  });

  it('puts a created item at the front when the sync did not add it', () => {
    const { box, list } = setup(false);
    box.createItem();
    expect(list.map((i) => i._id_)).toEqual(['N', 'A', 'B']);
  });
});
