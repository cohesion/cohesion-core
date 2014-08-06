<?php

class InvalidClassException extends Exception {}
class NotFoundException extends RuntimeException {}
class DataAccessException extends RuntimeException {}
class CacheException extends DataAccessException {}

// User Safe Exceptions are exceptions where the message is safe to display to the user
class UserSafeException extends RuntimeException {}
class UnauthorizedException extends UserSafeException {}
class UnauthorisedException extends UnauthorizedException {}
class MissingAssetException extends UserSafeException {}

