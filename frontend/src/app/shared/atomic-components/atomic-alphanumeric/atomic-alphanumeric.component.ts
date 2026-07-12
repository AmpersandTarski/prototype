import { Component } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-alphanumeric',
  templateUrl: './atomic-alphanumeric.component.html',
  styleUrls: ['./atomic-alphanumeric.component.css'],
})
export class AtomicAlphanumericComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
{
  /**
   * Bekende atomen voor autocomplete/validatie — blijft `null`.
   *
   * Dit is een ALPHANUMERIC-veld, dus het doelconcept is een scalar. De backend
   * weigert bewust `GET resource/<scalar>` (InterfaceNullObject::crudR geeft false
   * voor niet-object concepten), dus zo'n lijst opvragen levert altijd een 403 op.
   * Daarom doen we die request niet: `options` blijft null, er is geen client-side
   * optielijst/validatie, en de backend valideert de invoer. (Een echte 403 elders
   * wordt zo ook niet meer per ongeluk als "geen opties" weggeslikt.)
   */
  public options: string[] | null = null;

  /**
   * Slaat de waarde op die in het UNI-tekstveld stond bij focus.
   * Nodig om bij afgewezen invoer (validatie) het model terug te zetten,
   * want [(ngModel)] heeft de waarde al gemuteerd vóór de blur-handler.
   */
  public uniOriginalValue: string | null = null;

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
   * Voor scalars is `options` null (zie boven), dus valideren we niet client-side
   * en laat de backend de invoer beoordelen. Bij afwijzing wordt het model teruggezet.
   */
  public override updateValue(): void {
    if (!this.dirty) return;

    const newVal = (this.resource[this.propertyName] ?? '').trim();

    // Kleine c: mag geen nieuwe atomen aanmaken → valideer tegen bekende opties.
    // Alleen valideren als de optielijst beschikbaar is (options !== null).
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
   * Als !canCreate(): de nieuwe waarde moet in de opties staan (indien beschikbaar).
   * Voor scalars is `options` null, dus valideert de backend.
   */
  public validateAndUpdate(oldValue: string, newValue: string): void {
    const trimmed = newValue.trim();
    if (trimmed === String(oldValue)) return; // geen wijziging

    // Kleine c: mag geen nieuwe atomen aanmaken → valideer tegen opties.
    // Alleen valideren als de optielijst beschikbaar is (options !== null).
    if (!this.canCreate() && this.options !== null) {
      if (!this.options.includes(trimmed)) {
        return; // reject: invoer reset bij volgende render (input gebruikt [value]="row")
      }
    }

    this.updateItem(String(oldValue), trimmed);
  }
}
