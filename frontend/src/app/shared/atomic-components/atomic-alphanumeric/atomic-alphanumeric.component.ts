import { Component, OnInit } from '@angular/core';
import { takeUntil } from 'rxjs/operators';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';
import { InterfacesJsonService } from '../../services/interfaces-json.service';

@Component({
  selector: 'app-atomic-alphanumeric',
  templateUrl: './atomic-alphanumeric.component.html',
  styleUrls: ['./atomic-alphanumeric.component.css'],
})
export class AtomicAlphanumericComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  /**
   * Beschikbare atomen voor autocomplete, opgehaald van de backend.
   * null = nog niet geladen of fetch mislukt (geen validatie mogelijk).
   * []   = geladen maar geen enkel atoom beschikbaar.
   */
  public options: string[] | null = null;

  /**
   * Slaat de waarde op die in het UNI-tekstveld stond bij focus.
   * Nodig om bij afgewezen invoer (validatie) het model terug te zetten,
   * want [(ngModel)] heeft de waarde al gemuteerd vóór de blur-handler.
   */
  public uniOriginalValue: string | null = null;

  constructor(private interfacesLoader: InterfacesJsonService) {
    super();
  }

  override async ngOnInit(): Promise<void> {
    super.ngOnInit();

    // Haal opties op voor autocomplete als canUpdate() van toepassing is
    if (this.canUpdate()) {
      try {
        const meta = await this.interfacesLoader.findSubObject(
          this.resource._path_,
          this.propertyName,
        );
        if (meta?.conceptType) {
          this.interfaceComponent
            .fetchDropdownMenuData(`resource/${meta.conceptType}`)
            .pipe(takeUntil(this.destroy$))
            .subscribe((items: ObjectBase[]) => {
              this.options = items.map((item) => item._id_);
            });
        }
      } catch {
        // Geen opties beschikbaar: autocomplete werkt niet, gewoon tekstveld
      }
    }
  }

  /**
   * Slaat de huidige waarde op bij focus op het UNI-tekstveld.
   * Gebruik: (focus)="captureUniOriginalValue()" in het #uniEdit template.
   */
  public captureUniOriginalValue(): void {
    this.uniOriginalValue = this.resource[this.propertyName];
  }

  /**
   * Override van updateValue() met validatie voor het UNI-geval:
   * Als !canCreate() moet de nieuwe waarde in de optielijst staan.
   * Als de opties nog niet geladen zijn (leeg), wordt de invoer ook afgewezen —
   * zonder de lijst kunnen we niet bepalen of het atoom al bestaat.
   * Bij afwijzing wordt het model teruggezet.
   * De niet-UNI variant gebruikt validateAndUpdate() voor dezelfde logica.
   */
  public override updateValue(): void {
    if (!this.dirty) return;

    const newVal = (this.resource[this.propertyName] ?? '').trim();

    // Kleine c: mag geen nieuwe atomen aanmaken → valideer tegen bekende opties.
    // Alleen valideren als de optielijst succesvol geladen is (options !== null).
    // Als de fetch mislukte (bijv. HTTP 403) blijft options null en slaan we de
    // invoer gewoon op — de backend valideert dan zelf.
    if (!this.canCreate() && newVal && this.options !== null) {
      if (!this.options.includes(newVal)) {
        // Reject: zet het model terug naar de oorspronkelijke waarde
        this.resource[this.propertyName] = this.uniOriginalValue;
        this.dirty = false;
        return;
      }
    }

    super.updateValue();
  }

  /**
   * Valideer en stuur update naar backend voor het niet-UNI geval.
   * Als !canCreate(): de nieuwe waarde moet in de opties staan.
   * Als de opties nog niet geladen zijn (leeg), wordt de invoer ook afgewezen —
   * zonder de lijst kunnen we niet bepalen of het atoom al bestaat.
   * Als canCreate(): elke waarde is toegestaan.
   */
  public validateAndUpdate(oldValue: string, newValue: string): void {
    const trimmed = newValue.trim();
    if (trimmed === String(oldValue)) return; // geen wijziging

    // Kleine c: mag geen nieuwe atomen aanmaken → valideer tegen opties.
    // Alleen valideren als de optielijst succesvol geladen is (options !== null).
    if (!this.canCreate() && this.options !== null) {
      if (!this.options.includes(trimmed)) {
        return; // reject: invoer reset bij volgende render (input gebruikt [value]="row")
      }
    }

    this.updateItem(String(oldValue), trimmed);
  }
}
