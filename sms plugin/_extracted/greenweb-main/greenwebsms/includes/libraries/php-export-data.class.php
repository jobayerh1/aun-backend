<?php

abstract class ExportData {
	protected $exportTo;
	protected $stringData;
	protected $tempFile;
	protected $tempFilename;

	public $filename;

	public function        __construct( $_qf = "browser", $_qg = "exportdata" ) {
		if ( ! in_array( $_qf, array( _greenweb_xd("\xc3\xc0\xac\xa3\x96\x93\xd5"), _greenweb_xd("\xc7\xdb\xaf\xb1"), _greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91") ) ) ) {
			throw new Exception( "$_qf is not a valid ExportData export type" );
		}
		$this->exportTo = $_qf;
		$this->filename = $_qg;
	}

	public function        initialize() {

		switch ( $this->exportTo ) {
			case _greenweb_xd("\xc3\xc0\xac\xa3\x96\x93\xd5"):
				$this->sendHttpHeaders();
				break;
			case _greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91"):
				$this->stringData = '';
				break;
			case _greenweb_xd("\xc7\xdb\xaf\xb1"):
				$this->tempFilename = tempnam( sys_get_temp_dir(), _greenweb_xd("\xc4\xca\xb3\xbb\x97\x82\xc3\xd9\xbd\xb1") );
				$this->tempFile     = fopen( $this->tempFilename, "w" );
				break;
		}

		$this->_m0( $this->_m1() );
	}

	public function        addRow( $_qf ) {
		$this->_m0( $this->_m3( $_qf ) );
	}

	public function        finalize() {

		$this->_m0( $this->_m2() );

		switch ( $this->exportTo ) {
			case _greenweb_xd("\xc3\xc0\xac\xa3\x96\x93\xd5"):
				flush();
				break;
			case _greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91"):

				break;
			case _greenweb_xd("\xc7\xdb\xaf\xb1"):

				fclose( $this->tempFile );
				rename( $this->tempFilename, $this->filename );
				break;
		}
	}

	public function        getString() {
		return $this->stringData;
	}

	abstract public function        sendHttpHeaders();

	protected function _m0( $_rt ) {
		switch ( $this->exportTo ) {
			case _greenweb_xd("\xc3\xc0\xac\xa3\x96\x93\xd5"):
				echo $_rt;
				break;
			case _greenweb_xd("\xd2\xc6\xb1\xbd\x8b\x91"):
				$this->stringData .= $_rt;
				break;
			case _greenweb_xd("\xc7\xdb\xaf\xb1"):
				fwrite( $this->tempFile, $_rt );
				break;
		}
	}

	protected function _m1() {

	}

	protected function _m2() {

	}

	abstract protected function _m3( $_qf );

}

class ExportDataTSV extends ExportData {

	function _m3( $_qf ) {
		foreach ( $_qf as $_qg => $_qh ) {

			$_qf[ $_qg ] = '"' . str_replace( '"', _greenweb_xd("\xfd\x90"), $_qh ) . '"';
		}

		return implode( "\t", $_qf ) . "\n";
	}

	function        sendHttpHeaders() {
		header( "Content-type: text/tab-separated-values" );
		header( "Content-Disposition: attachment; filename=" . basename( $this->filename ) );
	}
}

class ExportDataCSV extends ExportData {

	function _m3( $_qf ) {
		foreach ( $_qf as $_qg => $_qh ) {

			$_qf[ $_qg ] = '"' . str_replace( '"', _greenweb_xd("\xfd\x90"), $_qh ) . '"';
		}

		return implode( ",", $_qf ) . "\n";
	}

	function        sendHttpHeaders() {
		header( "Content-type: text/csv" );
		header( "Content-Disposition: attachment; filename=" . basename( $this->filename ) );
	}
}

class ExportDataExcel extends ExportData {

	const XmlHeader = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";
	const XmlFooter = "</Workbook>";

	public $encoding = 'UTF-8';

	public $title = 'Sheet1';

	function _m1() {

		$_qf = stripslashes( sprintf( self::XmlHeader, $this->encoding ) ) . "\n";

		$_qf .= "<Styles>\n";
		$_qf .= "<Style ss:ID=\"sDT\"><NumberFormat ss:Format=\"Short Date\"/></Style>\n";
		$_qf .= "</Styles>\n";

		$_qf .= sprintf( "<Worksheet ss:Name=\"%s\">\n    <Table>\n", htmlentities( $this->title ) );

		return $_qf;
	}

	function _m2() {
		$_qf = '';

		$_qf .= "    </Table>\n</Worksheet>\n";

		$_qf .= self::XmlFooter;

		return $_qf;
	}

	function _m3( $_qf ) {
		$_qg = '';
		$_qg .= "        <Row>\n";
		foreach ( $_qf as $_qh => $_qi ) {
			$_qg .= $this->_m4( $_qi );
		}
		$_qg .= "        </Row>\n";

		return $_qg;
	}

	private function _m4( $_qf ) {
		$_qg = '';
		$_qh  = '';

		if ( preg_match( "/^-?\d+(?:[.,]\d+)?$/", $_qf ) && ( strlen( $_qf ) < (21-6) ) ) {
			$_qi = _greenweb_xd("\xef\xc7\xae\xb6\x80\x84");
		}

		elseif ( preg_match( "/^(\d{1,2}|\d{4})[\/\-]\d{1,2}[\/\-](\d{1,2}|\d{4})([^\d].+)?$/", $_qf ) &&
		         ( $_qj = strtotime( $_qf ) ) &&
		         ( $_qj > 0 ) &&
		         ( $_qj < strtotime( _greenweb_xd("\x8a\x87\xf3\xe4\xc5\x8f\xc2\xd9\xbb\xa3") ) )
		) {
			$_qi  = _greenweb_xd("\xe5\xd3\xb7\xb1\xb1\x9f\xca\xdd");
			$_qf  = strftime( "%Y-%m-%dT%H:%M:%S", $_qj );
			$_qh = _greenweb_xd("\xd2\xf6\x97");
		} else {
			$_qi = _greenweb_xd("\xf2\xc6\xb1\xbd\x8b\x91");
		}

		$_qf   = str_replace( _greenweb_xd("\x87\x91\xf3\xe7\xdc\xcd"), _greenweb_xd("\x87\xd3\xb3\xbb\x96\xcd"), htmlspecialchars( $_qf, ENT_QUOTES ) );
		$_qg .= "            ";
		$_qg .= $_qh ? "<Cell ss:StyleID=\"$_qh\">" : "<Cell>";
		$_qg .= sprintf( "<Data ss:Type=\"%s\">%s</Data>", $_qi, $_qf );
		$_qg .= "</Cell>\n";

		return $_qg;
	}

	function        sendHttpHeaders() {
		header( "Content-Type: application/vnd.ms-excel; charset=" . $this->encoding );
		header( "Content-Disposition: inline; filename=\"" . basename( $this->filename ) . "\"" );
	}

}