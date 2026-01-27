export interface DrugCategory {
  code: string;
  category_name: string;
  description: string;
  dispensing_rules: string[];
  labeling_requirement: string;
  storage_requirement: string;
  examples: string[];
}

export const DRUG_CATEGORIES: DrugCategory[] = [
  {
    code: "POM",
    category_name: "Prescription Only Medicine",
    description: "Drugs that require professional supervision due to potential toxicity, side effects, or risk of misuse. Can only be used under the direction of a qualified prescriber.",
    dispensing_rules: [
      "Valid prescription from a licensed Medical Doctor, Dentist, or Prescribing Pharmacist is MANDATORY.",
      "Pharmacist must verify prescription authenticity, patient details, drug, dosage, and instructions.",
      "No refills unless explicitly stated on the original prescription.",
      "Must be dispensed under the direct supervision of a registered pharmacist.",
      "Detailed sales records must be maintained for a minimum of 5 years."
    ],
    labeling_requirement: "Clearly marked as 'POM' on packaging and literature.",
    storage_requirement: "Stored behind the pharmacy counter, not accessible to the public.",
    examples: [
      "All antibiotics (Amoxicillin, Ciprofloxacin)",
      "Strong analgesics (Tramadol, Morphine, Codeine-containing products*)",
      "Antihypertensives (Amlodipine, Lisinopril)",
      "Antidiabetics (Insulin, Glibenclamide)",
      "Psychotropics (Diazepam, Amitriptyline)",
      "Antiretrovirals (ARVs)",
      "*Note: Codeine is strictly controlled as a Narcotic Drug."
    ]
  },
  {
    code: "P",
    category_name: "Pharmacy Only Medicine",
    description: "Medicines of intermediate safety that do not require a prescription but MUST be sold under the professional advice and supervision of a Registered Pharmacist.",
    dispensing_rules: [
      "NO prescription required.",
      "CANNOT be sold in chemical shops or non-pharmacy outlets.",
      "Must be sold FROM BEHIND THE COUNTER under the direct supervision and after consultation with a pharmacist.",
      "Pharmacist must assess patient suitability, provide counseling on correct use, and warn about side effects.",
      "Not for open public display or self-selection."
    ],
    labeling_requirement: "Often marked with a 'P' or stated as 'Pharmacy Medicine'.",
    storage_requirement: "Stored behind the pharmacy counter or in a supervised area.",
    examples: [
      "Emergency contraceptives (Levonorgestrel)",
      "Higher-strength topical corticosteroids (Mometasone cream)",
      "Some antifungal creams (Clotrimazole for longer-term use)",
      "Asthma relievers (Salbutamol inhalers)",
      "Dextromethorphan-based cough syrups (in certain strengths)"
    ]
  },
  {
    code: "OTC",
    category_name: "Over-The-Counter / General Sale List Medicine",
    description: "Medicines considered safe for public use for self-limiting conditions when used as directed on the label. Low risk of misuse.",
    dispensing_rules: [
      "No prescription or mandatory pharmacist consultation required.",
      "Can be sold in LICENSED PHARMACIES and LICENSED CHEMICAL SHOPS.",
      "Can be displayed on open shelves for self-selection.",
      "Seller (even in a chemical shop) should have basic FDA-approved training."
    ],
    labeling_requirement: "Labeled as 'OTC' or has no restrictive marking. Instructions must be clear for self-medication.",
    storage_requirement: "Can be stored on open shelves.",
    examples: [
      "Simple analgesics (Paracetamol, low-dose Ibuprofen)",
      "Antacids (Magnesium Trisilicate)",
      "Most vitamins and mineral supplements",
      "Simple laxatives (Senna)",
      "Barrier contraceptives (Condoms)",
      "Low-strength antiseptics (Hydrogen Peroxide)"
    ]
  },
  {
    code: "CD",
    category_name: "Controlled Drug (Narcotics & Psychotropic Substances)",
    description: "Drugs with a high potential for abuse, addiction, or diversion, as listed in the **Narcotics Drugs Commission Act** and international conventions. A strict subset of POM.",
    dispensing_rules: [
      "All POM rules apply, PLUS EXTREME restrictions.",
      "Requires a **Special FDA License** for the pharmacy to stock and dispense.",
      "Prescriptions must be in **duplicate/triplicate** with specific prescriber details and often have a validity limit (e.g., 7 days).",
      "Maximum quantity per prescription is strictly limited by law.",
      "Must be stored in a **dedicated, locked safe or cabinet** (double-locked system).",
      "Mandatory maintenance of a **Controlled Drugs Register** with details of every milligram received and dispensed. Subject to unannounced FDA inspections."
    ],
    labeling_requirement: "Marked with required international symbols (e.g., 'CD' or specific schedule).",
    storage_requirement: "In a locked safe, bolted to floor/wall, separate from other drugs.",
    examples: [
      "Morphine, Pethidine, Fentanyl",
      "Methadone",
      "Codeine phosphate (in pure form or above certain strengths)",
      "Methylphenidate (Ritalin)",
      "Certain strong benzodiazepines"
    ]
  }
];

export const getRegulatoryCodes = () => DRUG_CATEGORIES.map(c => c.code);
