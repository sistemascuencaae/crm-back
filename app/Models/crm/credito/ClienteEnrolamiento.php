<?php

namespace App\Models\crm\credito;

use App\Models\crm\Galeria;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteEnrolamiento extends Model
{
    use HasFactory;

    protected $table = 'crm.cliente_enrolamiento';
    protected $fillable = [
        "Uid",
        "StartingDate",
        "CreationDate",
        "CreationIP",
        "DocumentType",
        "IdNumber",
        "FirstName",
        "SecondName",
        "FirstSurname",
        "SecondSurname",
        "Gender",
        "BirthDate",
        "Street",
        "CedulateCondition",
        "Spouse",
        "Home",
        "MaritalStatus",
        "DateOfIdentification",
        "DateOfDeath",
        "MarriageDate",
        "Instruction",
        "PlaceBirth",
        "Nationality",
        "MotherName",
        "FatherName",
        "HouseNumber",
        "Profession",
        "ExpeditionCity",
        "ExpeditionDepartment",
        "BirthCity",
        "BirthDepartment",
        "TransactionType",
        "TransactionTypeName",
        "IssueDate",
        "BarcodeText",
        "OcrTextSideOne",
        "OcrTextSideTwo",
        "SideOneWrongAttempts",
        "SideTwoWrongAttempts",
        "FoundOnAdoAlert",
        "AdoProjectId",
        "TransactionId",
        "ProductId",
        "ComparationFacesSuccesful",
        "FaceFound",
        "FaceDocumentFrontFound",
        "BarcodeFound",
        "ResultComparationFaces",
        "ResultCompareDocumentFaces",
        "ComparationFacesAproved",
        "ThresholdCompareDocumentFaces",
        "CompareFacesDocumentResult",
        "Extras",
        "NumberPhone",
        "CodFingerprint",
        "ResultQRCode",
        "DactilarCode",
        "ReponseControlList",
        "Latitude",
        "Longitude",
        // "Images", no lo guardamos porque pesa mas de 8M en la base
        "SignedDocuments",
        "Scores",
        "Response_ANI",
        "Parameters",
        "StateSignatureDocument",

        "cli_id", // Id del cliente que viene desde el caso
        "caso_id", // Id del caso que viene desde el caso
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set("America/Guayaquil");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function imagenes()
    {
        return $this->hasMany(Galeria::class, "caso_id" , "caso_id");
    }
}
