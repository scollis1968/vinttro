That's great you have the basic query working. The method you're using is the standard and intended way to perform complex **filtering** with the SuiteCRM v8 REST API. It might seem crude compared to using a JSON body, but the API's design relies on **query parameters** for all filtering, searching, and sorting operations.

The URL you're using, `https://localhost/legacy/Api/V8/module/Contacts?filter[email1][eq]=test1@example.com`, is the correct and official format. You can build more complex queries by adding more filters, and using different operators and logical conditions, all within the URL.

***

## Building Complex Queries

### 1. Filtering with Multiple Conditions (AND)

You can chain multiple filters together to create an **"AND"** condition.

**Example**: Find a contact with a specific email AND a specific first name.

`https://localhost/legacy/Api/V8/module/Contacts?filter[email1][eq]=test1@example.com&filter[first_name][eq]=John`

* The `&` symbol is used to combine different filter conditions.

### 2. Using OR Conditions

For an **"OR"** condition, you use nested arrays within the `filter` parameter.

**Example**: Find a contact with a specific email OR a specific phone number.

`https://localhost/legacy/Api/V8/module/Contacts?filter[0][email1][eq]=test1@example.com&filter[1][phone_mobile][eq]=1234567890`

* The `[0]` and `[1]` indicate different conditions within the `filter` array, which the API treats as an "OR" logical grouping.

### 3. Using Different Operators

You aren't limited to just `[eq]`. Here are some other common operators:

* `[not_eq]`: Not equal to.
* `[starts_with]`: The field value starts with the specified string.
* `[contains]`: The field value contains the specified string.
* `[gt]`: Greater than.
* `[lt]`: Less than.
* `[is_null]`: Is the field null.

**Example**: Find all contacts whose last name contains the letters "smith".

`https://localhost/legacy/Api/V8/module/Contacts?filter[last_name][contains]=smith`

While the syntax can make long URLs, this approach is the **standard for REST APIs that follow the JSON API specification**, which SuiteCRM's v8 API is based on. It's designed to be stateless and to allow clients to define complex server-side filtering without sending a request body, which can be beneficial for caching and idempotency.

So, to answer your question: yes, this is the correct way, and you can build very complex queries just by manipulating the URL parameters. The API simply doesn't support building these filter queries in the body of a request.