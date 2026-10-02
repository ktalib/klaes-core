/**
 * Gender row tinting for the indexed-files tables.
 *
 * Male | Female | Corporate | Joint are the only values file_indexings.gender
 * ever holds — every write path folds onto them (App\Services\GenderNormalizer),
 * and Government bodies land on Corporate rather than a fifth value.
 *
 * A row with no recorded gender is left untinted on purpose: most legacy rows
 * predate the gender columns, so a colour there would read as data that isn't.
 * The classes are defined in public/css/app-layout.css, alongside the swatches
 * the legend uses (resources/views/components/gender-legend.blade.php) — colours
 * live in one place so the key always matches the rows.
 */
const GENDER_CLASSES = {
  MALE: 'gender-row-male',
  FEMALE: 'gender-row-female',
  CORPORATE: 'gender-row-corporate',
  JOINT: 'gender-row-joint',
};

/** Tint class for a row, or '' when the gender is missing or unrecognised. */
export function genderRowClass(gender) {
  const key = String(gender ?? '').trim().toUpperCase();

  return GENDER_CLASSES[key] || '';
}

/** Safe value for the row's data-gender attribute. */
export function genderAttr(gender) {
  const value = String(gender ?? '').trim();

  return GENDER_CLASSES[value.toUpperCase()] ? value : '';
}
