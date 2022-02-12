# Upgrading

## Upgrade from 4.0

If you're upgrading from Laraguard 4.0, you will need to check your code.

Laraguard 5.0 has removed the ```jsonSerialize()``` function in the trait ```SerializesSharedSecret```. If you need a JSON respresentation you need to use the ```toJson()``` function.


## Upgrade from 3.0

If you're upgrading from Laraguard 3.0, you will need to migrate.

Laraguard 4.0 encrypts the Shared Secret and Recovery Codes. This adds an extra layer of protection in case the database records are leaked to the wild, as recommended by the [RFC 6238](https://datatracker.ietf.org/doc/html/rfc6238).

To upgrade, ensure you have installed `doctrine/dbal` so the migration can run, as it needs to change a column type.

    composer require doctrine/dbal

Then, publish the upgrading migration and run it:

    php artisan vendor:publish --provider="DarkGhostHunter\Laraguard\LaraguardServiceProvider" --tag="upgrade"
    php artisan migrate

The migration will automatically encrypt all shared secrets, while also reverting the decryption on rolling back migrations.
