

```
gcloud auth application-default login
gcloud auth application-default set-quota-project reliable-brace-471109-c4
./cloud-sql-proxy --port 3308 reliable-brace-471109-c4:europe-west2:vinttro-uat-sql
```

```
CREATE TEMPORARY TABLE temp_page_cta AS
SELECT p1.ID, p1.post_title AS Page_Name,
    CASE
        WHEN p3.ID IS NOT NULL AND p2.ID IS NOT NULL THEN CONCAT(p3.post_title, ' / ', p2.post_title, ' / ', p1.post_title)
        WHEN p2.ID IS NOT NULL AND p3.ID IS NULL THEN CONCAT(p2.post_title, ' / ', p1.post_title)
        ELSE CONCAT('/ ', p1.post_title)
    END AS Full_Page_Path,
    CASE
        WHEN p1.post_content LIKE '%<!-- wp:block {"ref":%' THEN
            SUBSTRING_INDEX(SUBSTRING_INDEX(p1.post_content, '<!-- wp:block {"ref":', -1), '}', 1)
        ELSE 'No CTA Found'
    END AS CTA_Pattern_ID
FROM wp_posts p1
LEFT JOIN wp_posts p2 ON p1.post_parent = p2.ID
LEFT JOIN wp_posts p3 ON p2.post_parent = p3.ID
WHERE p1.post_type = 'page' AND p1.post_status = 'publish'
ORDER BY p1.ID;

SELECT P.*, CTA.post_title as CTA FROM temp_page_cta P
LEFT JOIN wp_posts CTA ON CTA.ID = P.CTA_Pattern_ID
ORDER BY P.Full_Page_Path;

DROP TABLE temp_page_cta;
```