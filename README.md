# Dolibarr BOM Stock Calculator (Calcul de Besoin)

A powerful Dolibarr ERP module designed to bridge the gap between Sales Orders (Commandes) and Manufacturing/BOMs (Nomenclatures). This module automatically calculates the exact component requirements needed to fulfill an order and provides a dedicated interface to manage stock reservations.

## ✨ Key Features

- **Automated Requirement Calculation:** Explodes the Bill of Materials (BOM) for each product on a Sales Order to determine the exact raw materials and components needed.
- **Stock Reservation System:** Easily reserve required components from your main warehouse to a dedicated "reservation" warehouse, ensuring components aren't accidentally used elsewhere.
- **Advanced PDF Reporting:** Generates a custom `Calcul de Besoin` PDF document directly from the Sales Order.
  - **Grouped Orderlines:** Intelligently groups orderlines that share the same `fusion_group_id`, aggregating their shared component needs into a single unified block.
  - **Live Stock Status:** Visually highlights components that are lacking in stock (red highlights).
  - **Shortages Summary:** Automatically appends a "Ressources manquantes" (Missing Resources) table at the end of the document, detailing exactly which components are missing and how much is lacking to fulfill the entire order.
  - **Reservation Status:** Tracks and displays if a component is "Non réservé", "Réservé", or "Consommé" directly on the document.
- **Float Quantities:** Fully supports decimal/float quantities for precise stock management.

## 🛠 Prerequisites

This module relies on the following ecosystem:
- **Dolibarr ERP/CRM** (v10.0+ recommended)
- **Factory Module:** A custom BOM/Manufacturing module must be installed and active (replaces core native BOM).
- *(Optional)* **Fusionlancement:** If you intend to use the grouped orderlines feature, your orderlines must utilize a `fusion_group_id` extrafield.

## 🚀 Installation

1. Download or clone this repository.
2. Place the `calcul_stock` folder inside your Dolibarr `custom` directory:
   ```bash
   htdocs/custom/calcul_stock/
   ```
3. Log into Dolibarr as an Administrator.
4. Go to **Setup -> Modules/Applications**.
5. Find the module under the **Custom Modules** tab (look for *Calcul Stock* or *Calcul de besoin*).
6. Click the toggle to enable it.
7. Configure the module settings (e.g., select your dedicated Reservation Warehouse).

## 💻 Usage

1. Create a Standard Sales Order (Commande) and add products that have an associated Factory BOM.
2. Navigate to the new **Calcul Stock / Réservation** tab on the Sales Order.
3. Review the exploded component list and their current stock levels.
4. Reserve components directly from the interface.
5. Generate the custom **Calcul de besoin** PDF to get a comprehensive report for your warehouse team, complete with a perforation line and shortages summary!

## 📄 License

This module is provided "as is". Feel free to fork, modify, and adapt it to your specific manufacturing workflows.
