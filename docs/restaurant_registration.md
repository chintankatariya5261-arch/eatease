# Restaurant Registration Workflow

- Restaurant form: name, cuisine, location, price range, phone, opening/closing, description, image.
- Menu management: add/edit/delete items with name, price, description, image.
- Preview: buttons on forms show a modal summary before submission.
- Transactions: inserts run in DB transactions with rollback on error.
- Backups: CSV lines appended in `backups/restaurants.csv` and `backups/menu_items.csv`.
- Display: owners see latest items; customers see all active restaurants and menus.
- APIs: GET `api/restaurants.php`, GET `api/menu_items.php?hotel_id=ID`.
