# SuiteCRM Custome styles.

SuiteCRM out of the box is a little bland, it would be good to find a way to jazz it up a little.

```
sudo chown -R www-data:www-data /var/www/suitecrm/public/legacy/custom/themes/SuiteP
```

You need to have the linked folders for the custom
```
cd /var/www/suitecrm/public/legacy/custom/themes/SuiteP/css
# Create the sub-theme folder
sudo mkdir -p Dawn
# Create a symlink so Dawn/style.css points to your main style.css
sudo ln -s ../style.css Dawn/style.css
```

```
cd /var/www/suitecrm/public/legacy/custom/themes/SuiteP/css
# Create the sub-theme folder
sudo mkdir -p Day
# Create a symlink so Day/style.css points to your main style.css
sudo ln -s ../style.css Day/style.css
```

sample from /var/www/suitecrm/public/legacy/custom/themes/suite8/css/style.css
```
/* 1. Reduce the excessive "whitespace" in subpanels */
.list-view td, .list-view th {
    padding: 8px 12px !important; /* Makes the rows tighter */
}

/* 2. Add a subtle shadow to the record panels */
.detail-view {
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

/* 3. Make the header text look more modern (Inter/Roboto style) */
h2, .module-title-text {
    font-weight: 700;
    letter-spacing: -0.025em;
    color: #111827;
}
```

nano  /var/www/suitecrm/public/dist/extensions/vinttro-custom-ui/css/custom.css