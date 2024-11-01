import { Component, Input, OnInit, Signal, computed } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-object',
  templateUrl: './atomic-object.component.html',
  styleUrls: ['./atomic-object.component.scss'],
})
export class AtomicObjectComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<ObjectBase, I>
  implements OnInit
{
  public dropdownMenuObjects: ObjectBase[] | undefined;
  @Input() public placeholder!: string;
  @Input() tgtResourceType!: string;

  override ngOnInit(): void {
    super.ngOnInit();

    if (this.canUpdate()) {
      // Find which entities are able to be added to the dropdown menu
      this.interfaceComponent
        .fetchDropdownMenuData(`resource/${this.tgtResourceType}`)
        .subscribe((objects) => {
          this.dropdownMenuObjects = objects;
        });
    }
  }

  public override removeItem(index: number) {
    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: `${this.propertyName}/${this.data[index]._id_}`,
        },
      ])
      .subscribe();
  }

  public deleteItem(index: number) {
    if (!confirm('Delete?')) return;

    this.interfaceComponent
      .delete(
        `${this.resource._path_}/${this.propertyName}/${this.data[index]._id_}`,
      )
      .subscribe();
  }

  /**
   * Exclude current values from dropdown menu
   */
  filteredDropdownMenuObjects: Signal<typeof this.dropdownMenuObjects> =
    computed(() => {
      const ids = this.requireArray(this.resource[this.propertyName]).map(
        (d) => d._id_,
      );
      return (
        this.dropdownMenuObjects?.filter((o) => !ids.includes(o._id_)) ?? []
      );
    });
}
